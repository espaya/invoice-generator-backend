<?php

namespace App\Http\Controllers;

use App\Mail\InvoiceMail;
use App\Models\Customer;
use App\Models\Invoice;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\CompanySetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = Auth::user();

            $search = trim($request->query("search", ""));

            $query = Invoice::with("customer", "items")
                ->where("user_id", $user->id);

            // ✅ Only search when search is filled
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where("invoice_number", "LIKE", "%{$search}%")
                        ->orWhere("status", "LIKE", "%{$search}%")
                        ->orWhere("invoice_date", "LIKE", "%{$search}%")
                        ->orWhereHas("customer", function ($c) use ($search) {
                            $c->where("name", "LIKE", "%{$search}%")
                                ->orWhere("email", "LIKE", "%{$search}%");
                        });
                });
            }

            $invoices = $query->orderBy("created_at", "desc")
                ->paginate(20);

            // ✅ Correct way to check paginator emptiness
            if ($invoices->count() === 0) {
                return response()->json(['message' => 'No invoices found.'], 404);
            }

            return response()->json($invoices, 200);
        } catch (\Exception $e) {
            Log::error("Error fetching invoices: " . $e->getMessage());

            return response()->json([
                "message" => "Failed to fetch invoices.",
                "error" => $e->getMessage()
            ], 500);
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
                    'address' => $newCustomer['address'] ?? "",
                    'phone' => $newCustomer['phone'] ?? "",
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

    public function update(Request $request, $invoice_number)
    {
        $request->validate([
            'customer_id' => 'nullable|exists:customers,id',

            'new_customer' => 'nullable|array',
            'new_customer.name'    => 'required_without:customer_id|string|max:255',
            'new_customer.email'   => 'required_without:customer_id|email|max:255',
            'new_customer.address' => 'required_without:customer_id|string|max:500',
            'new_customer.phone'   => 'required_without:customer_id|string|max:20',

            'invoice_date' => 'required|date',
            'due_date'     => 'required|date|after_or_equal:invoice_date',
            'status'       => 'required|in:paid,pending,overdue',
            'notes'        => 'nullable|string|max:1000',

            'items'               => 'required|array|min:1',
            'items.*.id'          => 'nullable|exists:invoice_items,id',
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity'    => 'required|numeric|min:1',
            'items.*.unit_price'  => 'required|numeric|min:0',

            'tax_percent' => 'nullable|numeric|min:0',
        ], [

            // Customer selection / new customer
            'customer_id.exists' => 'The selected customer does not exist.',

            'new_customer.required_without' => 'You must select an existing customer or enter a new customer.',

            'new_customer.name.required_without'    => 'Customer name is required when no existing customer is selected.',
            'new_customer.email.required_without'   => 'Customer email is required when no existing customer is selected.',
            'new_customer.address.required_without' => 'Customer address is required when no existing customer is selected.',
            'new_customer.phone.required_without'   => 'Customer phone is required when no existing customer is selected.',

            // Invoice dates
            'invoice_date.required' => 'Invoice date is required.',
            'invoice_date.date'     => 'Invoice date must be a valid date.',

            'due_date.required'       => 'Due date is required.',
            'due_date.date'           => 'Due date must be a valid date.',
            'due_date.after_or_equal' => 'Due date cannot be earlier than the invoice date.',

            // Status
            'status.required' => 'Invoice status is required.',
            'status.in'       => 'Invoice status must be either Paid, Pending, or Overdue.',

            // Notes
            'notes.string' => 'Notes must be valid text.',
            'notes.max'    => 'Notes cannot exceed 1000 characters.',

            // Items validation
            'items.required' => 'At least one invoice item is required.',
            'items.array'    => 'Invoice items must be in a valid format.',
            'items.min'      => 'At least one invoice item is required.',

            'items.*.id.exists' => 'One of the invoice items does not exist.',

            'items.*.description.required' => 'Each item must have a description.',
            'items.*.description.string'   => 'Item description must be valid text.',
            'items.*.description.max'      => 'Item description cannot exceed 255 characters.',

            'items.*.quantity.required' => 'Each item must have a quantity.',
            'items.*.quantity.numeric'  => 'Item quantity must be a valid number.',
            'items.*.quantity.min'      => 'Item quantity must be at least 1.',

            'items.*.unit_price.required' => 'Each item must have a unit price.',
            'items.*.unit_price.numeric'  => 'Item unit price must be a valid number.',
            'items.*.unit_price.min'      => 'Item unit price cannot be negative.',

            // Tax
            'tax_percent.numeric' => 'Tax percent must be a valid number.',
            'tax_percent.min'     => 'Tax percent cannot be negative.',
        ]);

        DB::beginTransaction();

        try {
            $invoice = Invoice::with('items')
                ->where('invoice_number', $invoice_number)
                ->where('user_id', Auth::id())
                ->first();

            if (!$invoice) {
                return response()->json([
                    "message" => "Invoice not found"
                ], 404);
            }

            $hasChanges = false;

            // ---------------- CUSTOMER ----------------
            $customerId = $request->customer_id;

            if (!$customerId && is_array($request->new_customer)) {
                $newCustomer = $request->new_customer;

                $customer = Customer::create([
                    'user_id' => Auth::id(),
                    'name' => $newCustomer['name'],
                    'email' => $newCustomer['email'],
                    'address' => $newCustomer['address'],
                    'phone' => $newCustomer['phone'] ?? "",
                ]);

                $customerId = $customer->id;
                $hasChanges = true;
            }

            // ---------------- TOTAL CALC ----------------
            $subtotal = collect($request->items)->sum(function ($item) {
                return $item['quantity'] * $item['unit_price'];
            });

            $taxPercent = $request->tax_percent ?? 0;

            // normalize decimals
            $subtotal = round($subtotal, 2);
            $taxPercent = round($taxPercent, 2);

            $taxAmount  = round(($subtotal * $taxPercent), 2);
            $total      = round(($subtotal + $taxAmount), 2);

            // ---------------- UPDATE INVOICE ----------------
            $invoice->customer_id  = $customerId;
            $invoice->invoice_date = $request->invoice_date;
            $invoice->due_date     = $request->due_date;
            $invoice->status       = $request->status;
            $invoice->notes        = $request->notes;

            $invoice->subtotal     = $subtotal;
            $invoice->tax_percent  = $taxPercent;
            $invoice->total        = $total;

            // detect invoice changes correctly
            if ($invoice->isDirty()) {
                $invoice->save();
                $hasChanges = true;
            }

            // ---------------- ITEMS UPDATE ----------------
            $existingItemIds = $invoice->items->pluck('id')->toArray();

            $incomingItemIds = collect($request->items)
                ->pluck('id')
                ->filter()
                ->toArray();

            // Delete removed items
            $itemsToDelete = array_diff($existingItemIds, $incomingItemIds);

            if (!empty($itemsToDelete)) {
                $invoice->items()->whereIn('id', $itemsToDelete)->delete();
                $hasChanges = true;
            }

            // Update or Create items
            foreach ($request->items as $itemData) {

                $lineTotal = round($itemData['quantity'] * $itemData['unit_price'], 2);

                if (!empty($itemData['id'])) {

                    $item = $invoice->items()->where('id', $itemData['id'])->first();

                    if ($item) {
                        $item->description = $itemData['description'];
                        $item->quantity    = $itemData['quantity'];
                        $item->unit_price  = round($itemData['unit_price'], 2);
                        $item->total       = $lineTotal;

                        if ($item->isDirty()) {
                            $item->save();
                            $hasChanges = true;
                        }
                    }
                } else {
                    $invoice->items()->create([
                        'description' => $itemData['description'],
                        'quantity'    => $itemData['quantity'],
                        'unit_price'  => round($itemData['unit_price'], 2),
                        'total'       => $lineTotal,
                    ]);

                    $hasChanges = true;
                }
            }

            // ✅ If nothing changed at all
            if (!$hasChanges) {
                DB::rollBack();

                return response()->json([
                    "message" => "No changes were made",
                    "invoice" => $invoice->fresh(['customer', 'items'])
                ], 200);
            }

            DB::commit();

            return response()->json([
                "message" => "Invoice updated successfully",
                "invoice" => $invoice->fresh(['customer', 'items'])
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Invoice update error: " . $e->getMessage());

            return response()->json([
                "message" => "Failed to update invoice"
            ], 500);
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

    public function downloadPdf($invoice_number)
    {
        try {

            $invoice = Invoice::with(['customer', 'items'])
                ->where('invoice_number', $invoice_number)
                ->first();

            if (!$invoice) {
                return response()->json(["message" => "Invoice not found"], 404);
            }

            // Example: load company settings from DB
            $companySettings = CompanySetting::first();

            $company = [
                "company_name" => $companySettings->company_name ?? "My Company",
                "company_address" => $companySettings->company_address ?? "",
                "company_email" => $companySettings->company_email ?? "",
                "company_phone" => $companySettings->company_phone ?? "",
            ];

            $currency = $companySettings->currency_symbol ?? "$";

            $pdf = Pdf::loadView("pdf.invoice", [
                "invoice" => $invoice,
                "company" => $company,
                "currency" => $currency,
            ]);

            return $pdf->download("invoice-" . $invoice->invoice_number . ".pdf");
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    public function sendInvoiceEmail($invoice_number)
    {
        try {
            $invoice = Invoice::with(['customer', 'items'])
                ->where("invoice_number", $invoice_number)
                ->first();

            if (!$invoice) {
                return response()->json(["message" => "Invoice not found"], 404);
            }

            if (!$invoice->customer || !$invoice->customer->email) {
                return response()->json(["message" => "Customer email not found"], 400);
            }

            $companySettings = CompanySetting::first();

            $company = [
                "company_name" => $companySettings->company_name ?? "My Company",
                "company_address" => $companySettings->company_address ?? "",
                "company_email" => $companySettings->company_email ?? "",
                "company_phone" => $companySettings->company_phone ?? "",
                "logo" => null
            ];

            $currency = $companySettings->currency_symbol ?? "$";

            Mail::to($invoice->customer->email)
                ->send(new InvoiceMail($invoice, $company, $currency));

            // mark invoice as sent
            $invoice->status = "sent";
            $invoice->sent_at = Carbon::now();
            $invoice->save();

            return response()->json([
                "message" => "Invoice sent successfully to " . $invoice->customer->email
            ]);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    public function markAsPaid($invoice_number)
    {
        try {
            DB::beginTransaction();

            $invoice = Invoice::where('invoice_number', $invoice_number)->first();

            if (!$invoice) {
                return response()->json(["message" => "Invoice not found"], 404);
            }

            $invoice->status = "paid";
            $invoice->save();

            DB::commit();

            return response()->json([
                "message" => "Invoice marked as paid",
                "invoice" => $invoice
            ]);
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    public function duplicateInvoice($invoice_number)
    {
        try {
            $invoice = Invoice::with('items')
                ->where('invoice_number', $invoice_number)
                ->first();

            if (!$invoice) {
                return response()->json(["message" => "Invoice not found"], 404);
            }

            $newInvoice = $invoice->replicate();
            $newInvoice->status = "pending";
            $newInvoice->invoice_number = "INV-" . now()->format("Ymd") . "-" . rand(1000, 9999);
            $newInvoice->save();

            foreach ($invoice->items as $item) {
                $newItem = $item->replicate();
                $newItem->invoice_id = $newInvoice->id;
                $newItem->save();
            }

            return response()->json([
                "message" => "Invoice duplicated successfully",
                "new_invoice" => $newInvoice
            ]);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    public function voidInvoice($invoice_number)
    {
        try {
            $invoice = Invoice::where('invoice_number', $invoice_number)->first();

            if (!$invoice) {
                return response()->json(["message" => "Invoice not found"], 404);
            }

            if ($invoice->status === "paid") {
                return response()->json(["message" => "Cannot void a paid invoice"], 400);
            }

            $invoice->status = "cancelled";
            $invoice->save();

            return response()->json([
                "message" => "Invoice cancelled successfully",
                "invoice" => $invoice
            ]);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    public function deleteInvoice($invoice_number)
    {
        try {
            $invoice = Invoice::where('invoice_number', $invoice_number)->first();

            if (!$invoice) {
                return response()->json(["message" => "Invoice not found"], 404);
            }

            $invoice->items()->delete();
            $invoice->delete();

            return response()->json([
                "message" => "Invoice deleted successfully"
            ]);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    public function recentInvoices()
    {
        try {
            $user = Auth::user();

            $invoices = Invoice::with('customer')->where("user_id", $user->id)
                ->latest()
                ->take(3)
                ->get();

            return response()->json(["invoices" => $invoices], 200);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    public function stats()
    {
        try {
            $user = Auth::user();

            // Total unique clients
            $totalClients = Customer::where("user_id", $user->id)->count();

            // Clients created this month
            $clientsThisMonth = Customer::where("user_id", $user->id)
                ->whereYear("created_at", now()->year)
                ->whereMonth("created_at", now()->month)
                ->count();

            // Top client (most invoices)
            $topClient = Customer::where("user_id", $user->id)
                ->withCount("invoices")
                ->orderByDesc("invoices_count")
                ->first();


            $totalInvoices = Invoice::where("user_id", $user->id)->count();

            $paidInvoices = Invoice::where("user_id", $user->id)
                ->where("status", "paid")
                ->count();

            $pendingInvoices = Invoice::where("user_id", $user->id)
                ->where("status", "pending")
                ->count();

            $overdueInvoices = Invoice::where("user_id", $user->id)
                ->where("status", "overdue")
                ->count();

            $totalRevenue = Invoice::where("user_id", $user->id)->sum("total");

            $paidRevenue = Invoice::where("user_id", $user->id)
                ->where("status", "paid")
                ->sum("total");

            $pendingRevenue = Invoice::where("user_id", $user->id)
                ->where("status", "pending")
                ->sum("total");

            $overdueRevenue = Invoice::where("user_id", $user->id)
                ->where("status", "overdue")
                ->sum("total");

            return response()->json([
                "total_revenue" => $totalRevenue,
                "paid_revenue" => $paidRevenue,
                "pending_revenue" => $pendingRevenue,
                "overdue_revenue" => $overdueRevenue,
                "total_invoices" => $totalInvoices,
                "overdue_invoices" => $overdueInvoices,
                "paid_invoices" => $paidInvoices,
                "pending_invoices" => $pendingInvoices,
                "total_clients" => $totalClients,
                "clients_this_month" => $clientsThisMonth,
                "top_client" => $topClient ? [
                    "id" => $topClient->id,
                    "name" => $topClient->name,
                    "invoices_count" => $topClient->invoices_count
                ] : null
            ], 200);
        } catch (\Exception $ex) {
            Log::error($ex->getMessage());
            return response()->json([
                "message" => "Failed to fetch clients stats",
            ], 500);
        }
    }
}
