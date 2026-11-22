<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductionSeedSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_in_production_creates_no_account_with_a_known_password(): void
    {
        // The suite runs as "testing"; make the seeder see production.
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $this->assertSame('production', app()->environment());
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);

        foreach (User::query()->get() as $user) {
            $this->assertFalse(
                Hash::check('password', (string) $user->password),
                'Seeding production created an account with the factory default password.',
            );
        }
    }

    public function test_seeding_in_local_still_creates_the_disposable_account(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }
}
