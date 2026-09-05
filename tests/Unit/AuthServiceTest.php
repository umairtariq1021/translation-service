<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_a_bearer_token_for_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $result = app(AuthService::class)->login([
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $this->assertSame('Bearer', $result['token_type']);
        $this->assertNotEmpty($result['token']);
        $this->assertTrue(
            PersonalAccessToken::query()
                ->where('tokenable_id', $user->id)
                ->exists()
        );
    }

    public function test_login_fails_for_unknown_email(): void
    {
        $this->expectException(ValidationException::class);

        app(AuthService::class)->login([
            'email' => 'missing@example.com',
            'password' => 'password',
        ]);
    }

    public function test_login_fails_for_incorrect_password(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $this->expectException(ValidationException::class);

        app(AuthService::class)->login([
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ]);
    }
}
