<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;


class CustomersController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = Auth::user();

            $search = trim($request->query("search", ""));

            $query = Customer::where("user_id", $user->id);

            // ✅ Only apply search if search is filled
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where("name", "LIKE", "%{$search}%")
                        ->orWhere("email", "LIKE", "%{$search}%")
                        ->orWhere("address", "LIKE", "%{$search}%");
                });
            }

            $customers = $query->orderBy("created_at", "desc")
                ->paginate(20);

            if ($customers->count() === 0) {
                return response()->json(["message" => "No customers found"], 200);
            }

            return response()->json($customers, 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                "message" => "Failed to fetch customers",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function existingCustomers()
    {
        try {

            $customers = Customer::orderBy("created_at", "desc")
                ->get();

            return response()->json($customers, 200);
        } catch (\Exception $e) {
            Log::error("Failed to fetch existing customers: " . $e->getMessage());

            return response()->json([
                "message" => "Failed to fetch existing customers",
                "error" => $e->getMessage()
            ], 500);
        }
    }
}
