<?php

namespace App\Http\Controllers;

use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;



class UserController extends Controller
{
    public function profile()
    {
        $user = Auth::user();
        $profile = $user->profile;

        return response()->json([
            "id" => $user->id,
            "username" => $user->name,
            "email" => $user->email,
            "photo" => $user->photo ? asset("storage/" . $user->photo) : null,

            "full_name" => $profile?->full_name,
            "phone" => $profile?->phone,
            "address" => $profile?->address,
            "city" => $profile?->city,
            "post_code" => $profile?->post_code,
            "country" => $profile?->country,
            "photo" => $profile?->photo ? asset("storage/" . $profile->photo) : null,

        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'username' => [
                'required',
                'string',
                'max:255',
                // Rule::unique('users', 'name')->ignore($user->id),
            ],

            'full_name'  => 'required|string|max:255',

            'phone'      => 'nullable|string|max:50',
            'address'    => 'nullable|string|max:255',
            'city'       => 'nullable|string|max:100',
            'post_code'  => 'nullable|string|max:50',
            'country'    => 'nullable|string|max:100',
        ], [
            'username.required' => 'Username is required.',
            'username.string'   => 'Username must be a valid string.',
            'username.max'      => 'Username cannot be more than 255 characters.',
            // 'username.unique'   => 'This username is already taken.',

            'full_name.required' => 'Full name is required.',
            'full_name.string'   => 'Full name must be a valid string.',
            'full_name.max'      => 'Full name cannot be more than 255 characters.',

            'phone.string' => 'Phone number must be a valid string.',
            'phone.max'    => 'Phone number cannot be more than 50 characters.',

            'address.string' => 'Address must be a valid string.',
            'address.max'    => 'Address cannot be more than 255 characters.',

            'city.string' => 'City must be a valid string.',
            'city.max'    => 'City cannot be more than 100 characters.',

            'post_code.string' => 'Post code must be a valid string.',
            'post_code.max'    => 'Post code cannot be more than 50 characters.',

            'country.string' => 'Country must be a valid string.',
            'country.max'    => 'Country cannot be more than 100 characters.',
        ]);


        try 
        {

            $user = Auth::user();
            $userNameExists = User::where('name', $request->username)
                ->where('id', '!==', $user->id)
                ->exists();

            if ($userNameExists) {
                return response()->json([
                    'message' => 'Username already exists!'
                ], 422);
            }


            // Ensure profile exists
            $profile = $user->profile()->firstOrCreate([
                "user_id" => $user->id
            ]);

            // USER TABLE
            $user->name = $request->username;

            // PROFILE TABLE
            $profile->full_name = $request->full_name;
            $profile->phone = $request->phone;
            $profile->address = $request->address;
            $profile->city = $request->city;
            $profile->post_code = $request->post_code;
            $profile->country = $request->country;

            $userDirty = $user->isDirty();
            $profileDirty = $profile->isDirty();

            if (!$userDirty && !$profileDirty) {
                return response()->json([
                    "message" => "No changes were made"
                ], 200);
            }

            if ($userDirty) {
                $user->save();
            }

            if ($profileDirty) {
                $profile->save();
            }

            return response()->json([
                "message" => "Profile updated successfully",
                "user" => [
                    "id" => $user->id,
                    "username" => $user->name,
                    "email" => $user->email,
                    "photo" => $user->photo ? asset("storage/" . $user->photo) : null,

                    "full_name" => $profile->full_name,
                    "phone" => $profile->phone,
                    "address" => $profile->address,
                    "city" => $profile->city,
                    "post_code" => $profile->post_code,
                    "country" => $profile->country,
                ]
            ], 200);
        } catch (Exception $ex) {
            Log::error("Profile update error: " . $ex->getMessage());

            return response()->json([
                "message" => "An unexpected error occurred"
            ], 500);
        }
    }

    public function updateEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email,' . Auth::id(),
        ], [
            'email.required' => 'This field is required',
            'email.email' => 'This field is invalid',
            'email.unique' => 'Email already exists'
        ]);

        try {
            $user = Auth::user();

            if ($user->email === $request->email) {
                return response()->json([
                    "message" => "No changes were made"
                ], 200);
            }

            $user->email = $request->email;
            $user->save();

            return response()->json([
                "message" => "Email updated successfully"
            ], 200);
        } catch (Exception $ex) {
            Log::error("Email update error: " . $ex->getMessage());

            return response()->json([
                "message" => "An unexpected error occurred"
            ], 500);
        }
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',

            'new_password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
        ], [
            'new_password.required' => 'New password is required.',
            'new_password.confirmed' => 'Password confirmation does not match.',
        ]);


        try {
            $user = Auth::user();

            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    "message" => "Current password is incorrect"
                ], 422);
            }

            $user->password = Hash::make($request->new_password);
            $user->save();

            return response()->json([
                "message" => "Password updated successfully"
            ], 200);
        } catch (Exception $ex) {
            Log::error("Password update error: " . $ex->getMessage());

            return response()->json([
                "message" => "An unexpected error occurred"
            ], 500);
        }
    }

    public function updatePhoto(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:20480'
        ], [
            'photo.required' => 'Please select an image',
            'photo.image' => 'The uploaded file must be an image',
            'photo.mimes' => 'Allowed image types: jpg, jpeg, png, webp',
            'photo.max' => 'Image size must not exceed 20MB',
        ]);

        DB::beginTransaction();

        $path = null; // important

        try {
            $user = Auth::user();

            // Ensure profile exists safely
            $profile = $user->profile()->firstOrCreate(
                ["user_id" => $user->id],
                [
                    "full_name" => "",
                    "phone" => "",
                    "address" => "",
                    "city" => "",
                    "post_code" => "",
                    "country" => "",
                    "photo" => "",
                ]
            );

            // Ensure directory exists
            $directory = "profile_photos";

            if (!Storage::disk("public")->exists($directory)) {
                Storage::disk("public")->makeDirectory($directory);
                @chmod(storage_path("app/public/" . $directory), 0755);
            }

            // Upload new file first
            $path = $request->file("photo")->store($directory, "public");

            // Save old photo for deletion AFTER commit
            $oldPhoto = $profile->photo;

            // Save new photo path
            $profile->photo = $path;

            if (!$profile->save()) {
                Storage::disk("public")->delete($path);
                DB::rollBack();

                return response()->json([
                    "message" => "Failed to update profile photo"
                ], 500);
            }

            DB::commit();

            // delete old photo after successful commit
            if ($oldPhoto && Storage::disk("public")->exists($oldPhoto)) {
                Storage::disk("public")->delete($oldPhoto);
            }

            return response()->json([
                "message" => "Profile photo updated successfully",
                "photo_url" => asset("storage/" . $path)
            ], 200);
        } catch (Exception $ex) {

            DB::rollBack();

            // delete uploaded image if error happened
            if ($path && Storage::disk("public")->exists($path)) {
                Storage::disk("public")->delete($path);
            }

            Log::error("Photo update error: " . $ex->getMessage());

            return response()->json([
                "message" => "An unexpected error occurred"
            ], 500);
        }
    }
}
