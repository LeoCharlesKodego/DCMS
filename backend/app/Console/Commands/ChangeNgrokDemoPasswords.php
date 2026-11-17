<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class ChangeNgrokDemoPasswords extends Command
{
    protected $signature = 'demo:change-ngrok-passwords';
    protected $description = 'Set new private passwords for every temporary sharing demo account.';

    private const DEMO_EMAILS = [
        'process.owner@example.test', 'kmt@example.test', 'division.head@example.test',
        'mcc@example.test', 'rmic@example.test', 'system.admin@example.test',
        'staff.recipient@example.test',
    ];

    // The System Administrator account is rotated but never listed for testers.
    private const TESTER_EMAILS = [
        'process.owner@example.test', 'kmt@example.test', 'division.head@example.test',
        'mcc@example.test', 'rmic@example.test', 'staff.recipient@example.test',
    ];

    public function handle(): int
    {
        $databasePath = realpath((string) config('database.connections.sqlite.database'));
        $expected = realpath(base_path('local-demo/ngrok/database.sqlite'));
        if (app()->environment() !== 'ngrok' || config('database.default') !== 'sqlite' || ! $databasePath || $databasePath !== $expected) {
            $this->error('Refusing: the active environment is not the isolated ngrok demo SQLite database.');
            return self::FAILURE;
        }

        $users = User::query()->whereIn('email', self::DEMO_EMAILS)->orderBy('email')->get();
        if ($users->count() !== count(self::DEMO_EMAILS)) {
            $this->error('Expected all seven fake demo accounts. Run the isolated demo seed first.');
            return self::FAILURE;
        }

        $credentials = [];
        foreach ($users as $user) $credentials[$user->email] = bin2hex(random_bytes(16));
        $secretFile = base_path('../.local-private/dcms-demo-logins.txt');
        if (! is_dir(dirname($secretFile)) && ! mkdir(dirname($secretFile), 0700, true) && ! is_dir(dirname($secretFile))) {
            $this->error('Could not create the ignored local secrets folder.');
            return self::FAILURE;
        }

        $lines = ['Temporary fictional DCMS demo logins', 'Share only with the intended testers.', ''];
        foreach ($credentials as $email => $password) {
            if (in_array($email, self::TESTER_EMAILS, true)) $lines[] = $email.'  '.$password;
        }
        if (file_put_contents($secretFile, implode(PHP_EOL, $lines).PHP_EOL, LOCK_EX) === false) {
            $this->error('Could not write the ignored local demo login file.');
            return self::FAILURE;
        }
        @chmod($secretFile, 0600);
        DB::transaction(function () use ($users, $credentials): void {
            foreach ($users as $user) {
                $user->forceFill(['password' => Hash::make($credentials[$user->email]), 'must_change_password' => false])->save();
            }
        });

        $marker = base_path('local-demo/ngrok/PASSWORDS_CHANGED');
        file_put_contents($marker, now()->toIso8601String().PHP_EOL, LOCK_EX);
        @chmod($marker, 0600);
        $this->info('Passwords changed for all seven fake demo accounts. Values were saved only to the ignored .local-private/dcms-demo-logins.txt file.');
        return self::SUCCESS;
    }
}
