<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function register(array $data): array
    {
        $idCardPath = isset($data['id_card'])
            ? $data['id_card']->store('kyc', 'private')
            : null;

        return DB::transaction(function () use ($data, $idCardPath): array {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => $data['role'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'organization' => $data['organization'] ?? null,
                'id_card_path' => $idCardPath,
                'verification_status' => $data['role'] === 'creator' ? 'pending' : 'not_required',
            ]);

            return [
                'user' => $user,
                'token' => $user->createToken('frontend')->plainTextToken,
            ];
        });
    }

    public function login(array $data): array
    {
        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau kata sandi tidak sesuai.'],
            ]);
        }

        return [
            'user' => $user,
            'token' => $user->createToken('frontend')->plainTextToken,
        ];
    }

    public function logout(User $user, ?string $tokenId): void
    {
        if ($tokenId !== null) {
            $user->tokens()->whereKey($tokenId)->delete();
        }
    }
}
