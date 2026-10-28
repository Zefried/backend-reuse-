<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class AuthService
{
    /**
     * Authenticate user with email or phone and password.
     */
    public function login(array $data): array
    {
        try {
            if (empty($data['email']) && empty($data['phone'])) {
                return [
                    'status'  => false,
                    'message' => 'Email or phone is required.',
                    'code'    => 422,
                ];
            }

            $validator = Validator::make($data, [
                'email'    => 'nullable|string',
                'phone'    => 'nullable|string|max:20',
                'password' => 'required|string',
            ]);

            if ($validator->fails()) {
                return [
                    'status'  => false,
                    'message' => $validator->errors()->first(),
                    'code'    => 422,
                ];
            }

            $identifier = $data['email'] ?? $data['phone'] ?? null;

            $user = User::where(function ($query) use ($identifier) {
                $query->where('email', $identifier)
                      ->orWhere('phone', $identifier);
            })->first();

            if (!$user || !Hash::check($data['password'], $user->password)) {
                return [
                    'status'  => false,
                    'message' => 'Invalid credentials.',
                    'code'    => 401,
                ];
            }

            $token = $user->createToken('auth-token')->plainTextToken;

            return [
                'status' => true,
                'data'   => [
                    'user'  => $user,
                    'token' => $token,
                ],
                'code'   => 200,
            ];
        } catch (Throwable $e) {
            Log::error('AuthService::login error: ' . $e->getMessage());
            return [
                'status'  => false,
                'message' => 'An error occurred during login. Please try again.',
                'code'    => 500,
            ];
        }
    }

    /**
     * Revoke current access token.
     */
    public function logout(?User $user): array
    {
        try {
            if ($user && method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
                $user->currentAccessToken()->delete();
            }

            return [
                'status'  => true,
                'message' => 'Logged out successfully.',
                'code'    => 200,
            ];
        } catch (Throwable $e) {
            Log::error('AuthService::logout error: ' . $e->getMessage());
            return [
                'status'  => false,
                'message' => 'Failed to logout. Please try again.',
                'code'    => 500,
            ];
        }
    }

    /**
     * Create a user by an Admin with a selected role (sub_admin or user).
     */
    public function createUserByAdmin(array $data): array
    {
        try {
            if (empty($data['email']) && empty($data['phone'])) {
                return [
                    'status'  => false,
                    'message' => 'Either email or phone is required.',
                    'code'    => 422,
                ];
            }

            $validator = Validator::make($data, [
                'name'     => 'required|string|max:255',
                'email'    => 'nullable|email|max:255|unique:users,email',
                'phone'    => 'nullable|string|max:20|unique:users,phone',
                'password' => 'required|string|min:6',
                'role'     => 'required|string|in:sub_admin,user',
            ]);

            if ($validator->fails()) {
                return [
                    'status'  => false,
                    'message' => $validator->errors()->first(),
                    'code'    => 422,
                ];
            }

            $validated = $validator->validated();

            $user = User::create([
                'name'     => $validated['name'],
                'email'    => $validated['email'] ?? null,
                'phone'    => $validated['phone'] ?? null,
                'password' => Hash::make($validated['password']),
                'role'     => $validated['role'],
            ]);

            return [
                'status'  => true,
                'message' => 'User created successfully.',
                'data'    => [
                    'user' => $user,
                ],
                'code'    => 201,
            ];
        } catch (Throwable $e) {
            Log::error('AuthService::createUserByAdmin error: ' . $e->getMessage());
            return [
                'status'  => false,
                'message' => 'Failed to create user. Please try again.',
                'code'    => 500,
            ];
        }
    }

    /**
     * List all users created (for Admin Panel view).
     */
    public function listUsers(): array
    {
        try {
            $users = User::select('id', 'name', 'email', 'phone', 'role', 'created_at')
                ->where('role', '!=', 'admin')
                ->orderBy('id', 'desc')
                ->get();

            return [
                'status' => true,
                'data'   => $users,
                'code'   => 200,
            ];
        } catch (Throwable $e) {
            Log::error('AuthService::listUsers error: ' . $e->getMessage());
            return [
                'status'  => false,
                'message' => 'Failed to fetch users.',
                'code'    => 500,
            ];
        }
    }
}
