<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class DevAccount extends Command
{
    protected $signature = 'dcms:dev-account
        {--email=admin@dcms.local : Sign-in address for the local account}
        {--name=Local Test Administrator : Display name for the local account}
        {--with-notifications : Add one read and one unread sample notification}
        {--role= : Optional ONP role code to assign, for a fuller local navigation}';

    protected $description = 'Create or refresh a disposable local sign-in account in the SQLite development database';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->components->error('Refused: this command is only available outside production.');

            return self::FAILURE;
        }

        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->components->error('Refused: this command only writes to the local SQLite development database.');

            return self::FAILURE;
        }

        $email = mb_strtolower(trim((string) $this->option('email')), 'UTF-8');
        $password = (string) (getenv('DCMS_DEV_PASSWORD') ?: '');
        if ($password === '') {
            $password = (string) $this->secret('Local account password (at least 12 characters)');
            $confirmation = (string) $this->secret('Confirm local account password');
            if (! hash_equals($password, $confirmation)) {
                $this->components->error('The password confirmation does not match.');

                return self::FAILURE;
            }
        }

        $validator = Validator::make([
            'email' => $email,
            'password' => $password,
        ], [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', Password::min(12)],
        ]);

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first());

            return self::FAILURE;
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => trim((string) $this->option('name')) ?: 'Local Test Administrator',
                'password' => $password,
                'is_active' => true,
                'is_system_admin' => true,
                'must_change_password' => false,
            ],
        );

        $roleCode = trim((string) $this->option('role'));
        if ($roleCode !== '') {
            $role = Role::query()->where('code', $roleCode)->first();
            if (! $role) {
                $this->components->warn('Role '.$roleCode.' was not found; no role was assigned.');
            } else {
                $user->roleAssignments()->updateOrCreate(
                    ['role_id' => $role->id],
                    [
                        'organizational_unit_id' => null,
                        'work_scope' => 'Local development',
                        'valid_from' => now()->toDateString(),
                        'valid_until' => null,
                    ],
                );
            }
        }

        if ($this->option('with-notifications')) {
            DB::table('user_notifications')->where('user_id', $user->id)->where('event_type', 'local.dev_sample')->delete();
            DB::table('user_notifications')->insert([
                [
                    'user_id' => $user->id,
                    'event_type' => 'local.dev_sample',
                    'title' => 'Sample unread notification',
                    'body' => 'Local development data only.',
                    'read_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'user_id' => $user->id,
                    'event_type' => 'local.dev_sample',
                    'title' => 'Sample read notification',
                    'body' => 'Local development data only.',
                    'read_at' => now(),
                    'created_at' => now()->subDay(),
                    'updated_at' => now()->subDay(),
                ],
            ]);
        }

        $this->components->info($user->wasRecentlyCreated
            ? 'Created the local account '.$email.'.'
            : 'Refreshed the local account '.$email.'.');
        $this->line('This account exists only in database/local-dev.sqlite. Do not use it in production.');

        return self::SUCCESS;
    }
}
