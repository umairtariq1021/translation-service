<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * @param array<string, string> $credentials
     * @return array<string, string>
     */
    public function login(array $credentials): array
    {
        $user = User::query()
            ->where('email', $credentials['email'])
            ->first();

        if (
            !$user ||
            !Hash::check($credentials['password'], $user->password)
        ) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user
            ->createToken('translation-api')
            ->plainTextToken;

        return [
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }
}