<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminInvoiceController extends Controller
{
    public function index(Request $request)
    {
        try {
            $search = $request->query('search');

            $invoices = Invoice::with('user')
                ->when($search, function ($query) use ($search) {
                    $query->where('invoice_number', 'LIKE', "%{$search}%")
                        ->orWhere('status', 'LIKE', "%{$search}%")
                        ->orWhere('amount', 'LIKE', "%{$search}%")
                        ->orWhereHas('user', function ($q) use ($search) {
                            $q->where('name', 'LIKE', "%{$search}%")
                                ->orWhere('email', 'LIKE', "%{$search}%");
                        });
                })
                ->orderBy('id', 'DESC')
                ->paginate(10);

            return response()->json($invoices, 200);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $invoice = Invoice::with('customer')->find($id);

            if (!$invoice) {
                return response()->json(['message' => 'Invoice not found'], 404);
            }

            $invoice->delete();

            return response()->json(['message' => 'Invoice deleted successfully'], 200);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());

            return response()->json([
                'message' => 'An unexpected error occurred'
            ], 500);
        }
    }
}
