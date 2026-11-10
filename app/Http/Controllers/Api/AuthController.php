<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return response()->json([
            'status' => true,
            'message' => $result['user']->role === 'creator'
                ? 'Pendaftaran berhasil. Akun CC dan donatur menunggu verifikasi Admin untuk fitur campaign.'
                : 'Pendaftaran berhasil.',
            'data' => $result,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Login berhasil.',
            'data' => $this->authService->login($request->validated()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout(
            $request->user(),
            $request->user()->currentAccessToken()?->getKey(),
        );

        return response()->json([
            'status' => true,
            'message' => 'Logout berhasil.',
            'data' => null,
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Profil berhasil dimuat.',
            'data' => $request->user(),
        ]);
    }
}
