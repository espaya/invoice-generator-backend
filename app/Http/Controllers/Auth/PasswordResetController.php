<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
use App\Models\User;


class PasswordResetController extends Controller
{
    /**
     * Send reset password link to email
     */
    public function sendResetLink(Request $request)
    {
        $request->validate([
            "token" => "required",
            "email" => "required|email|exists:users,email",

            "password" => [
                "required",
                "confirmed",
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised()
            ],
        ], [
            "token.required" => "Reset token is required",
            "email.required" => "Email is required",
            "email.exists" => "Email does not exist",

            "password.required" => "Password is required",
            "password.confirmed" => "Passwords do not match",
            "password.min" => "Password must be at least 8 characters",
        ]);

        $status = Password::sendResetLink(
            $request->only("email")
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                "message" => "Password reset link has been sent to your email."
            ], 200);
        }

        return response()->json([
            "message" => "Unable to send reset link. Try again later."
        ], 500);
    }

    /**
     * Reset password
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            "token" => "required",
            "email" => "required|email|exists:users,email",
            "password" => "required|min:8|confirmed",
        ], [
            "token.required" => "Reset token is required",
            "email.required" => "Email is required",
            "email.exists" => "Email does not exist",
            "password.required" => "Password is required",
            "password.confirmed" => "Passwords do not match",
        ]);

        $status = Password::reset(
            $request->only("email", "password", "password_confirmation", "token"),
            function (User $user, string $password) {
                $user->forceFill([
                    "password" => Hash::make($password),
                    "remember_token" => Str::random(60),
                    "force_password_reset" => false, // optional (if you added this feature)
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                "message" => "Password has been reset successfully."
            ], 200);
        }

        return response()->json([
            "message" => "Invalid token or email."
        ], 422);
    }
}
