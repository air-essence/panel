<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_admin(): void
    {
        $this->artisan('panel:dodaj-uzytkownika', ['email' => 'gabriel@example.com', '--name' => 'Gabriel', '--role' => 'admin'])
            ->expectsQuestion('Hasło (min. 10 znaków)', 'bardzotajne123')
            ->assertSuccessful();

        $user = User::firstWhere('email', 'gabriel@example.com');
        $this->assertSame(Role::Admin, $user->role);
        $this->assertTrue(Hash::check('bardzotajne123', $user->password));
    }

    public function test_command_rejects_short_password_and_bad_role(): void
    {
        $this->artisan('panel:dodaj-uzytkownika', ['email' => 'a@example.com', '--name' => 'A', '--role' => 'szef'])
            ->assertFailed();

        $this->artisan('panel:dodaj-uzytkownika', ['email' => 'a@example.com', '--name' => 'A'])
            ->expectsQuestion('Hasło (min. 10 znaków)', 'krotkie')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }
}
