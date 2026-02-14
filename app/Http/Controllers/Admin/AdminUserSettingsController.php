<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminUserSettingsController extends Controller
{
    // ==========================
    // UPDATE STATUS + BLOCK LOGIN
    // ==========================
    public function updateStatus(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $validated = $request->validate([
                'status' => 'required|in:active,suspended,banned',
                'is_blocked' => 'required|boolean',
            ]);

            $user->update($validated);

            return response()->json([
                'message' => 'User status updated successfully',
                'user' => $user
            ], 200);
        } catch (Exception $ex) {
            Log::error("Update Status Error: " . $ex->getMessage());

            return response()->json([
                'message' => 'Failed to update status'
            ], 500);
        }
    }

    // ==========================
    // UPDATE PERMISSIONS
    // ==========================
    public function updatePermissions(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $validated = $request->validate([
                'can_create_invoice' => 'required|boolean',
                'can_download_pdf' => 'required|boolean',
                'can_send_email' => 'required|boolean',
            ]);

            $user->update($validated);

            return response()->json([
                'message' => 'User permissions updated successfully',
                'user' => $user
            ], 200);
        } catch (Exception $ex) {
            Log::error("Update Permissions Error: " . $ex->getMessage());

            return response()->json([
                'message' => 'Failed to update permissions'
            ], 500);
        }
    }

    // ==========================
    // UPDATE SECURITY SETTINGS
    // ==========================
    public function updateSecurity(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $validated = $request->validate([
                'force_password_reset' => 'required|boolean',
            ]);

            $user->update($validated);

            return response()->json([
                'message' => 'User security updated successfully',
                'user' => $user
            ], 200);
        } catch (Exception $ex) {
            Log::error("Update Security Error: " . $ex->getMessage());

            return response()->json([
                'message' => 'Failed to update security settings'
            ], 500);
        }
    }


    // ==========================
    // UPDATE ADMIN NOTES
    // ==========================
    public function updateNotes(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $validated = $request->validate([
                'admin_notes' => 'nullable|string',
            ]);

            $user->update([
                'admin_notes' => $validated['admin_notes'] ?? null
            ]);

            return response()->json([
                'message' => 'Admin notes updated successfully',
                'user' => $user
            ], 200);
        } catch (Exception $ex) {
            Log::error("Update Notes Error: " . $ex->getMessage());

            return response()->json([
                'message' => 'Failed to update notes'
            ], 500);
        }
    }
}
