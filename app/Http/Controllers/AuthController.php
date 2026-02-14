<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            "email" => "required|email",
            "password" => "required"
        ], [
            'email.required' => 'Email is required',
            'email.email' => 'Email must be a valid email address',
            'password.required' => 'Password is required',
        ]);

        if (!Auth::guard('web')->attempt($request->only("email", "password"))) {
            return response()->json([
                "message" => "Invalid credentials"
            ], 401);
        }

        $user = Auth::user();

        // ================================
        // BLOCK LOGIN FOR RESTRICTED USERS
        // ================================
        if ($user->status === "banned") {
            Auth::logout();

            return response()->json([
                "message" => "Your account has been banned. Contact support."
            ], 403);
        }

        if ($user->force_password_reset) {
            Auth::logout();

            return response()->json([
                "message" => "Password reset required. Please reset your password to continue.",
                "force_password_reset" => true,
            ], 403);
        }


        if ($user->status === "suspended") {
            Auth::logout();

            return response()->json([
                "message" => "Your account is suspended. Contact support."
            ], 403);
        }

        if ($user->is_blocked) {
            Auth::logout();

            return response()->json([
                "message" => "Your account has been blocked. Contact support."
            ], 403);
        }

        // regenerate session only after passing restrictions
        $request->session()->regenerate();

        $redirect_url = match ($user->role) {
            'admin' => '/admin/dashboard',
            'user' => '/user/dashboard',
            default => '/',
        };

        return response()->json([
            "message" => "Login successful",
            "user" => [
                "id" => $user->id,
                "name" => $user->name,
                "email" => $user->email,
                "role" => $user->role,
            ],
            "redirect_url" => $redirect_url
        ], 200);
    }


    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            "message" => "Logged out successfully"
        ], 200);
    }

    public function user(Request $request)
    {
        return response()->json([
            "id" => $request->user()->id,
            "name" => $request->user()->name,
            "email" => $request->user()->email,
            "role" => $request->user()->role,
        ]);
    }
}
