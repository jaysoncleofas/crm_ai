<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** Session-cookie login for the SPA (Sanctum stateful). */
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->credentials(), $request->boolean('remember'))) {
            // Identical message for unknown email and wrong password — no user enumeration.
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $user = $request->user();

        if (! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            throw ValidationException::withMessages([
                'email' => 'This account has been deactivated.',
            ]);
        }

        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        activity('auth')
            ->causedBy($user)
            ->withProperties(['ip' => $request->ip()])
            ->log('User signed in');

        return response()->json([
            'data' => UserResource::make($user->load(['roles.permissions', 'permissions'])),
        ]);
    }

    /** Self-service signup. The first account bootstraps as admin. */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $isFirstUser = User::query()->withTrashed()->doesntExist();

            $user = User::create([
                'name' => $request->string('name')->trim()->value(),
                'email' => strtolower($request->string('email')->trim()->value()),
                'password' => $request->string('password')->value(),
                'is_active' => true,
            ]);

            $user->assignRole($isFirstUser ? 'admin' : 'sales_rep');

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'data' => UserResource::make($user->load(['roles.permissions', 'permissions'])),
        ], 201);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => UserResource::make($request->user()->load(['roles.permissions', 'permissions'])),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        activity('auth')->causedBy($request->user())->log('User signed out');

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Signed out.']);
    }
}
