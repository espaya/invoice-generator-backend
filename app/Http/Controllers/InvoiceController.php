<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceController extends Controller
{
    // Only fetch invoices of the logged-in user
    public function index()
    {
        try {

            $invoices = Invoice::with('customer', 'items')
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            if ($invoices->isEmpty()) {
                return response()->json(['message' => 'No invoices found.'], 404);
            }

            return response()->json($invoices);
        } catch (Exception $e) {
            Log::error('Error fetching invoices: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch invoices.'], 500);
        }
    }

    // Store new invoice
    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'new_customer.name'    => 'required_without:customer_id|string|max:255',
            'new_customer.email'   => 'required_without:customer_id|email|max:255',
            'new_customer.address' => 'required_without:customer_id|string|max:500',
            'new_customer.phone'   => 'required_without:customer_id|string|max:20',

            'invoice_date' => 'required|date',
            'due_date'     => 'required|date|after_or_equal:invoice_date',
            'status'       => 'required|in:paid,pending,overdue',
            'notes'        => 'nullable|string|max:1000',

            'items'               => 'required|array|min:1',
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity'    => 'required|numeric|min:1',
            'items.*.unit_price'  => 'required|numeric|min:0',

            'tax_percent' => 'nullable|numeric|min:0',

        ], [
            'customer_id.exists' => 'The selected customer does not exist.',

            'new_customer.name.required_without'    => 'Name is required when no existing customer is selected.',
            'new_customer.email.required_without'   => 'Email is required when no existing customer is selected.',
            'new_customer.address.required_without' => 'Address is required when no existing customer is selected.',
            'new_customer.phone.required_without'   => 'Phone is required when no existing customer is selected.',

            'invoice_date.required' => 'Invoice date is required.',
            'due_date.required'     => 'Due date is required.',
            'due_date.after_or_equal' => 'Due date cannot be before the invoice date.',

            'status.required' => 'Invoice status is required.',
            'status.in'       => 'Invalid status selected.',

            'items.required'               => 'At least one item is required.',
            'items.min'                    => 'At least one item is required.',
            'items.*.description.required' => 'Item description is required.',
            'items.*.quantity.required'    => 'Item quantity is required.',
            'items.*.quantity.min'         => 'Item quantity must be at least 1.',
            'items.*.unit_price.required'  => 'Item unit price is required.',
            'items.*.unit_price.min'       => 'Item unit price cannot be negative.',
        ]);


        DB::beginTransaction();

        try {

            $subtotal = collect($request->items)->sum(function ($item) {
                return $item['quantity'] * $item['unit_price'];
            });

            $taxPercent = $request->tax_percent ?? 0;
            $taxAmount  = ($subtotal * $taxPercent) / 100;
            $total      = $subtotal + $taxAmount;


            // 1️⃣ Save customer (if new)
            $customerId = $request->customer_id;
            if (!$customerId && $request->new_customer) {
                $newCustomer = $request->new_customer;
                $customer = Customer::create([
                    'name' => $newCustomer['name'],
                    'email' => $newCustomer['email'],
                    'address' => $newCustomer['address'] ?? null,
                    'user_id' => Auth::id(),
                ]);
                $customerId = $customer->id;
            }

            // prefix 
            $prefix = Auth::user()->companySetting->invoice_prefix ?? "INV";

            // 2️⃣ Generate unique invoice number
            $lastInvoice = Invoice::latest()->first();
            $nextId = $lastInvoice ? $lastInvoice->id + 1 : 1;
            $invoiceNumber = $prefix . date('Ymd') . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

            // 3️⃣ Save invoice
            $invoice = auth()->user()->invoices()->create([
                'invoice_number' => $invoiceNumber,
                'customer_id' => $customerId,
                'invoice_date' => $request->invoice_date,
                'due_date' => $request->due_date,
                'subtotal' => $subtotal,
                'tax_percent' => $request->tax_percent ?? 0,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'status' => $request->status,
                'notes' => $request->notes ?? null,
            ]);

            // 4️⃣ Save invoice items
            foreach ($request->items as $item) {
                $invoice->items()->create([
                    'description' => $item['description'],
                    'quantity'    => $item['quantity'],
                    'unit_price'  => $item['unit_price'],
                    'total'       => $item['quantity'] * $item['unit_price'],
                ]);
            }

            // Send invoice creation email (optional)


            DB::commit();

            return response()->json([
                'message' => 'Invoice created successfully',
                'invoice_number' => $invoiceNumber,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating invoice: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to create invoice.'], 500);
        }
    }

    public function view($invoice_number)
    {
        try {
            $invoice = Invoice::with(['customer', 'items'])->where('invoice_number', $invoice_number)->first();

            if (!$invoice) {
                return response()->json(["message" => "Invoice $invoice_number not found!"], 404);
            }

            return response()->json($invoice, 200);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }
}
