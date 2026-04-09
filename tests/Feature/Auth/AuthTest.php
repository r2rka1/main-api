<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $this->postJson('/api/register', [
            'name'                  => 'Ada',
            'email'                 => 'ada@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertStatus(201)
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);

        $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
    }

    public function test_register_validates_input(): void
    {
        $this->postJson('/api/register', [
            'name'     => '',
            'email'    => 'not-an-email',
            'password' => 'short',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_user_can_login_and_get_token(): void
    {
        User::create([
            'name'     => 'Grace',
            'email'    => 'grace@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email'    => 'grace@example.com',
            'password' => 'password123',
        ])->assertStatus(200)
          ->assertJsonStructure(['user' => ['id', 'email'], 'token']);

        $token = $response->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertStatus(200)
            ->assertJsonPath('data.email', 'grace@example.com');
    }

    public function test_login_rejects_bad_credentials(): void
    {
        User::create([
            'name'     => 'Linus',
            'email'    => 'linus@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/login', [
            'email'    => 'linus@example.com',
            'password' => 'wrong',
        ])->assertStatus(422);
    }

    public function test_logout_revokes_token(): void
    {
        $user  = User::create([
            'name'     => 'Ken',
            'email'    => 'ken@example.com',
            'password' => Hash::make('password123'),
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout')
            ->assertStatus(204);

        // Token row is gone — subsequent auth attempts using it would fail.
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
