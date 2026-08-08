<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_available(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('data-page="login"', false);
    }

    public function test_users_can_authenticate_and_reach_dashboard(): void
    {
        $user = User::factory()->create([
            'email' => 'dev@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson(route('login'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('redirectTo', route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'dev@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson(route('login'), [
            'email' => 'dev@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
        $this->assertGuest();
    }

    public function test_login_rejects_inactive_user(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => bcrypt('password'),
            'inactivated_at' => now(),
        ]);

        $response = $this->postJson(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
        $this->assertGuest();
    }
}
