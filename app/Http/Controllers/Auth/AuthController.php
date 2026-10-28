<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    private AuthService $auth;

    public function __construct(AuthService $auth)
    {
        $this->auth = $auth;
    }

    public function login(Request $request): JsonResponse
    {
        $result = $this->auth->login($request->all());

        return response()->json(
            $result,
            $result['code'] ?? ($result['status'] ? 200 : 400)
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $result = $this->auth->logout($request->user());

        return response()->json(
            $result,
            $result['code'] ?? 200
        );
    }

    public function createUser(Request $request): JsonResponse
    {
        $result = $this->auth->createUserByAdmin($request->all());

        return response()->json(
            $result,
            $result['code'] ?? ($result['status'] ? 201 : 400)
        );
    }

    public function listUsers(): JsonResponse
    {
        $result = $this->auth->listUsers();

        return response()->json(
            $result,
            $result['code'] ?? 200
        );
    }
}
