<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;


class AuthController extends Controller
{
    public function login(Request $request)
    {
        $rules = [
            "email" => "required|email",
            "password" => "required",
        ];

        // only require captcha in production
        if (!app()->environment('local')) {
            $rules["recaptcha_token"] = "required";
        }

        $request->validate($rules, [
            'email.required' => 'Email is required',
            'email.email' => 'Email must be a valid email address',
            'password.required' => 'Password is required',
            "recaptcha_token.required" => "Token is required"
        ]);

        // verify captcha only in production
        if (!app()->environment('local')) {
            $captcha = $this->verifyRecaptcha($request->recaptcha_token);

            if (!($captcha["success"] ?? false)) {
                return response()->json([
                    "message" => "Captcha verification failed"
                ], 422);
            }
        }

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

        if ($user->force_password_reset) {
            Auth::logout();

            return response()->json([
                "message" => "Password reset required. Please reset your password to continue.",
                "force_password_reset" => true,
            ], 403);
        }

        // regenerate session only after passing restrictions
        $request->session()->regenerate();


        $settingsUrl = "/admin/dashboard";

        try {
            $companySettings = CompanySetting::first();

            if (!$companySettings && !$companySettings->company_name) {
                $settingsUrl = "/admin/dashboard/settings/system";
            }
        } catch (\Exception $e) {
            // if company_settings table doesn't exist yet
            $settingsUrl = "/admin/dashboard/settings/system";
        }


        $redirect_url = match ($user->role) {
            'admin' => $settingsUrl,
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

    private function verifyRecaptcha($token)
    {
        $response = Http::asForm()->post("https://www.google.com/recaptcha/api/siteverify", [
            "secret" => env("RECAPTCHA_SECRET_KEY"),
            "response" => $token,
        ]);

        return $response->json();
    }
}
