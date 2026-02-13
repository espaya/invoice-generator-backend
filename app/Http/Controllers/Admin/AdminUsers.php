<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Storage;

class AdminUsers extends Controller
{
    public function index(Request $request)
    {
        try {
            $search = $request->query('search');

            $users = User::where('role', 'user')
                ->when($search, function ($query) use ($search) {
                    $query->where('name', 'LIKE', "%$search%")
                        ->orWhere('email', 'LIKE', "%$search%");
                })
                ->orderBy('name', 'ASC')
                ->paginate(10);

            if ($users->isEmpty()) {
                return response()->json(['message' => 'No users found'], 404);
            }

            return response()->json($users, 200);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }

            $user->delete();

            return response()->json(['message' => 'User deleted successfully'], 200);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return response()->json(['message' => 'Unexpected error'], 500);
        }
    }


    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:users,name',
            'email' => 'required|email|unique:users,email',

            'password' => [
                'required',
                'string',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
            ],

            'role' => 'required|string',
            'full_name' => 'required|string|max:255',
            'phone' => [
                'required',
                'string',
                'max:50',
                'regex:/^\+?[0-9\s\-\(\)]{7,20}$/'
            ],

            'address' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'post_code' => 'required|string|max:50',
            'country' => 'required|string|max:100',
            'photo' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ], [
            'name.required' => 'Username is required.',
            'name.unique' => 'This username is already taken.',
            'name.max' => 'Username must not exceed 255 characters.',

            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email already exists.',

            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.mixed_case' => 'Password must contain at least one uppercase and one lowercase letter.',
            'password.numbers' => 'Password must contain at least one number.',
            'password.symbols' => 'Password must contain at least one symbol.',

            'role.required' => 'Role is required.',

            'photo.image' => 'Photo must be an image file.',
            'photo.mimes' => 'Photo must be a jpg, jpeg, or png file.',
            'photo.max' => 'Photo must not be larger than 2MB.',

            'phone.max' => 'Phone number must not exceed 50 characters.',
            'address.max' => 'Address must not exceed 255 characters.',
            'city.max' => 'City must not exceed 100 characters.',
            'post_code.max' => 'Post code must not exceed 50 characters.',
            'country.max' => 'Country must not exceed 100 characters.',
            'phone.regex' => 'Phone number must be valid (local or international format).',

        ]);

        try {
            DB::beginTransaction();

            $photoPath = null;

            $dir = "profile_photos";

            if ($request->hasFile('photo')) {

                if (!Storage::disk('public')->exists($dir)) {
                    Storage::disk('public')->makeDirectory($dir);
                }

                $photoPath = $request->file('photo')->store($dir, 'public');
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role,
            ]);

            Profile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'full_name' => $request->full_name,
                    'phone' => $request->phone,
                    'address' => $request->address,
                    'city' => $request->city,
                    'post_code' => $request->post_code,
                    'country' => $request->country,
                    'photo' => $photoPath ?? Profile::where('user_id', $user->id)->value('photo'),
                ]
            );


            DB::commit();

            return response()->json([
                'message' => 'User created successfully',
                'user' => $user
            ], 201);
        } catch (\Exception $ex) {

            DB::rollBack();

            // delete uploaded photo if error happens
            if ($photoPath && Storage::disk('public')->exists($photoPath)) {
                Storage::disk('public')->delete($photoPath);
            }

            Log::error($ex->getMessage());

            return response()->json([
                'message' => $ex->getMessage()
            ], 500);
        }
    }
}
