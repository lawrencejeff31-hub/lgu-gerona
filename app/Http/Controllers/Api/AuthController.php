<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAuthRequest;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(StoreAuthRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        // Log user registration as an auth event
        \App\Services\AuditLogger::logAuthEvent('register', $user, [
            'email' => $user->email,
        ]);

        return ApiResponse::created([
            'user' => $user,
            'token' => $token,
        ], 'User registered successfully');
    }

    public function login(StoreAuthRequest $request)
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            // Log failed login attempt (user may not be authenticated yet)
            \App\Services\AuditLogger::logAuthEvent('login_failed', null, [
                'email' => $request->email,
            ]);
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = \App\Models\User::findOrFail(Auth::id());
        $token = $user->createToken('auth-token')->plainTextToken;


        $roles = $user->getRoleNames();
        $permissions = $user->getAllPermissions()->pluck('name');

        // Log successful login
        \App\Services\AuditLogger::logAuthEvent('login', $user, [
            'email' => $user->email,
        ]);

        return ApiResponse::success([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $roles,
                'permissions' => $permissions,
            ],
            'token' => $token,
        ], 'Login successful');
    }

    public function logout(Request $request)
    {
        // Log logout event before token deletion
        if ($request->user()) {
            \App\Services\AuditLogger::logAuthEvent('logout', $request->user());
        }

        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(null, 'Logged out successfully');
    }

    public function me(Request $request)
    {
        $user = $request->user();
        return ApiResponse::success([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ], 'User profile retrieved successfully');
    }

    public function refresh(Request $request)
    {
        $user = \App\Models\User::findOrFail($request->user()->id);
        $user->tokens()->delete();
        $token = $user->createToken('auth-token')->plainTextToken;

        return ApiResponse::success([
            'user' => $user,
            'token' => $token,
        ], 'Token refreshed successfully');
    }
}