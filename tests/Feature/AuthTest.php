<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/logowanie');
    }

    public function test_user_can_log_in_and_out(): void
    {
        $user = User::factory()->create(['email' => 'anna@example.com', 'password' => 'tajnehaslo123']);

        $this->post('/logowanie', ['email' => 'anna@example.com', 'password' => 'tajnehaslo123'])
            ->assertRedirect('/');
        $this->assertAuthenticatedAs($user);

        $this->post('/wyloguj')->assertRedirect('/logowanie');
        $this->assertGuest();
    }

    public function test_wrong_password_shows_polish_error(): void
    {
        User::factory()->create(['email' => 'anna@example.com']);

        $this->from('/logowanie')
            ->post('/logowanie', ['email' => 'anna@example.com', 'password' => 'zle'])
            ->assertRedirect('/logowanie')
            ->assertSessionHasErrors(['email' => 'Nieprawidłowy e-mail lub hasło.']);
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        User::factory()->create(['email' => 'anna@example.com', 'password' => 'tajnehaslo123', 'is_active' => false]);

        $this->post('/logowanie', ['email' => 'anna@example.com', 'password' => 'tajnehaslo123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        User::factory()->create(['email' => 'anna@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/logowanie', ['email' => 'anna@example.com', 'password' => 'zle']);
        }

        $this->post('/logowanie', ['email' => 'anna@example.com', 'password' => 'zle'])
            ->assertSessionHasErrors(['email']);
        $this->assertStringContainsString('Zbyt wiele prób', session('errors')->first('email'));
    }
}
