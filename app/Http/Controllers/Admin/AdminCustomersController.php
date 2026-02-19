<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;


class AdminCustomersController extends Controller
{
    public function index(Request $request)
    {
        try {
            $search = $request->query('search');

            $customers = Customer::query()
                ->when($search, function ($query) use ($search) {
                    $query->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%")
                        ->orWhere('phone', 'LIKE', "%{$search}%");
                })
                ->orderBy('name', 'ASC')
                ->paginate(10);

            if ($customers->isEmpty()) {
                return response()->json(['message' => 'No customers found'], 404);
            }

            return response()->json($customers, 200);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $customer = Customer::find($id);

            if (!$customer) {
                return response()->json(['message' => 'Customer not found'], 404);
            }

            $customer->delete();

            return response()->json(['message' => 'Customer deleted successfully'], 200);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    public function view($id)
    {
        try {
            $customer = Customer::with('invoices')->where('id', $id)->first();

            if (!$customer) {
                return response()->json(['message' => 'Customer not found'], 404);
            }

            return response()->json($customer, 200);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $customer = Customer::find($id);

        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('customers', 'email')->ignore($customer->id)
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
        ], [
            'name.required' => 'Customer name is required.',
            'email.required' => 'Customer email is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email is already taken.',
            'phone.max' => 'Phone number is too long.',
            'address.max' => 'Address is too long.',
        ]);

        try {

            DB::beginTransaction();

            // Only update fields if they were sent (filled)
            if ($request->filled('name')) {
                $customer->name = $request->name;
            }

            if ($request->filled('email')) {
                $customer->email = $request->email;
            }

            if ($request->filled('phone')) {
                $customer->phone = $request->phone;
            }

            if ($request->filled('address')) {
                $customer->address = $request->address;
            }

            // If nothing changed
            if (!$customer->isDirty()) {
                return response()->json([
                    'message' => 'No changes detected',
                    'customer' => $customer
                ], 200);
            }

            $customer->save();

            DB::commit();

            return response()->json([
                'message' => 'Customer updated successfully',
                'customer' => $customer
            ], 200);
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }
}
