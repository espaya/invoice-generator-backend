<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomersController extends Controller
{
    public function index()
    {
        try {
            // Fetch customers from the database
            $customers = Customer::all();

            if ($customers->isEmpty()) {
                return response()->json(['message' => 'No customers found'], 404);
            }

            // Return customers as JSON response
            return response()->json($customers, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch customers', 'message' => $e->getMessage()], 500);
        }
    }
}
