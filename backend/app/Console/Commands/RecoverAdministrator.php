<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class RecoverAdministrator extends Command
{
    protected $signature = 'dcms:recover-admin {email : Email of the active administrator to recover}';

    protected $description = 'Recover an administrator account with two-person authorization';

    public function handle(): int
    {
        $user = User::query()
            ->where('email', mb_strtolower(trim($this->argument('email')), 'UTF-8'))
            ->where('is_system_admin', true)
            ->where('is_active', true)
            ->first();

        if (! $user) {
            $this->components->error('No active system administrator with that email was found.');

            return self::FAILURE;
        }

        $personOne = trim($this->ask('First authorized person (verify identity in person)'));
        $personTwo = trim($this->ask('Second authorized person (verify identity in person)'));
        $reason = trim($this->ask('Reason for emergency recovery'));

        if ($personOne === '' || $personTwo === '' || mb_strtolower($personOne, 'UTF-8') === mb_strtolower($personTwo, 'UTF-8') || $reason === '') {
            $this->components->error('Two different authorized people and a reason are required.');

            return self::FAILURE;
        }

        $password = $this->secret('Temporary administrator password (at least 12 characters)');
        $confirmation = $this->secret('Confirm temporary password');
        $passwordValidator = Validator::make(['password' => $password], [
            'password' => ['required', 'string', Password::min(12)],
        ]);

        if ($passwordValidator->fails() || ! hash_equals($password, $confirmation)) {
            $this->components->error($passwordValidator->errors()->first('password') ?: 'The password confirmation does not match.');

            return self::FAILURE;
        }

        $this->components->warn('Verify that no active administrator can sign in and that both people are authorized before continuing.');

        if (! $this->confirm('Both people authorize recovery of '.$user->name.' ('.$user->email.')?', false)) {
            $this->components->info('No account was changed.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($user, $personOne, $personTwo, $reason, $password): void {
            $user->forceFill([
                'password' => $password,
                'must_change_password' => true,
                'remember_token' => Str::random(60),
            ])->save();

            DB::table('account_security_events')->insert([
                'actor_user_id' => null,
                'actor_name' => $personOne,
                'second_authorized_person' => $personTwo,
                'target_user_id' => $user->id,
                'target_name' => $user->name,
                'target_email' => $user->email,
                'event_type' => 'emergency_administrator_recovery',
                'reason' => $reason,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->components->info('Recovery was recorded. The administrator must change the temporary password at next sign-in.');

        return self::SUCCESS;
    }
}
