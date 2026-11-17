<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class BootstrapAdministrator extends Command
{
    protected $signature = 'dcms:bootstrap-admin';

    protected $description = 'Create the first DCMS system administrator interactively';

    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->components->error('Bootstrap is allowed only before the first user account is created.');

            return self::FAILURE;
        }

        $name = trim($this->ask('Administrator full name'));
        $email = mb_strtolower(trim($this->ask('Administrator email')), 'UTF-8');
        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first());

            return self::FAILURE;
        }

        $password = $this->secret('Administrator password (at least 12 characters)');
        $confirmation = $this->secret('Confirm administrator password');

        $passwordValidator = Validator::make([
            'password' => $password,
        ], [
            'password' => ['required', 'string', Password::min(12)],
        ]);

        if ($passwordValidator->fails() || ! hash_equals($password, $confirmation)) {
            $this->components->error($passwordValidator->errors()->first('password') ?: 'The password confirmation does not match.');

            return self::FAILURE;
        }

        if (! $this->confirm('Create the first system administrator now?', true)) {
            $this->components->info('No administrator account was created.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($name, $email, $password): void {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'is_system_admin' => true,
                'is_active' => true,
                'must_change_password' => false,
            ]);

            DB::table('account_security_events')->insert([
                'actor_user_id' => $user->id,
                'actor_name' => $user->name,
                'second_authorized_person' => null,
                'target_user_id' => $user->id,
                'target_name' => $user->name,
                'target_email' => $user->email,
                'event_type' => 'bootstrap_administrator_created',
                'reason' => 'Initial administrator bootstrap',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->components->info('The first DCMS system administrator was created.');
        $this->components->warn('Create a second individually named system administrator through the user administration module when it is available.');

        return self::SUCCESS;
    }
}
