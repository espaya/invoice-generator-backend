<?php

namespace App\Http\Controllers;

use App\Mail\InvoiceMail;
use App\Models\Customer;
use App\Models\Invoice;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\CompanySetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;


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
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity'    => 'required|numeric|min:1',
            'items.*.unit_price'  => 'required|numeric|min:0',
            'items.*.image'       => 'nullable|file|image|mimes:jpeg,jpg,png,gif,webp,avif|max:5120',
            'tax_percent' => 'nullable|numeric|min:0|max:100',
        ], [
            // 
        ]);

        try {
            $user = Auth::user();

            if (!$user || !$user->can_create_invoice) {
                return response()->json([
                    'message' => "Sorry you're not allowed to create an invoice"
                ], 403);
            }

            // Calculate totals
            $subtotal = collect($request->items)->sum(function ($item) {
                return $item['quantity'] * $item['unit_price'];
            });

            $taxPercent = $request->tax_percent ?? 0;
            $taxAmount = ($subtotal * $taxPercent) / 100;
            $total = $subtotal + $taxAmount;

            // Handle customer
            $customerId = $request->customer_id;
            if (!$customerId && $request->new_customer) {
                $newCustomer = $request->new_customer;
                $customer = Customer::where('email', $newCustomer['email'])->first();

                if (!$customer) {
                    $customer = Customer::create([
                        'name' => $newCustomer['name'],
                        'email' => $newCustomer['email'],
                        'address' => $newCustomer['address'] ?? "",
                        'phone' => $newCustomer['phone'] ?? "",
                        'user_id' => Auth::id(),
                    ]);
                }
                $customerId = $customer->id;
            }

            // Generate invoice number
            $companySettings = CompanySetting::first();
            $prefix = $companySettings->invoice_prefix ?? "INV";
            $lastInvoice = Invoice::latest()->first();
            $nextId = $lastInvoice ? $lastInvoice->id + 1 : 1;
            $invoiceNumber = $prefix . date('Ymd') . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

            // 🔥 FIX 1: Create invoice WITHOUT transaction first
            $invoice = auth()->user()->invoices()->create([
                'invoice_number' => $invoiceNumber,
                'customer_id' => $customerId,
                'invoice_date' => $request->invoice_date,
                'due_date' => $request->due_date,
                'subtotal' => $subtotal,
                'tax_percent' => $taxPercent,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'status' => $request->status,
                'notes' => $request->notes ?? null,
                'public_token' => Str::random(60)
            ]);

            // 🔥 FIX 2: Create items WITHOUT images first (separate from transaction)
            $imagePaths = [];

            foreach ($request->items as $index => $item) {
                $imagePath = null;

                // Upload image if exists (do this BEFORE saving to DB)
                if (isset($item['image']) && $item['image'] instanceof \Illuminate\Http\UploadedFile) {
                    try {
                        Log::info("Processing image for item {$index}", [
                            'original_name' => $item['image']->getClientOriginalName(),
                            'size' => $item['image']->getSize(),
                            'mime' => $item['image']->getMimeType()
                        ]);

                        // Generate unique filename
                        $filename = 'inv_item_' . $invoice->id . '_' . $index . '_' . time() . '_' . uniqid() . '.' . $item['image']->getClientOriginalExtension();

                        // Store the file
                        $storedPath = $item['image']->storeAs('invoice-items', $filename, 'public');

                        if ($storedPath) {
                            $imagePath = '/storage/' . $storedPath;
                            $imagePaths[] = $imagePath;
                            Log::info("Image stored successfully", ['path' => $imagePath]);
                        }
                    } catch (\Exception $e) {
                        Log::error("Image upload failed for item {$index}: " . $e->getMessage());
                        // Don't stop, just continue without image
                    }
                }

                // Create invoice item with or without image
                $invoice->items()->create([
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total' => $item['quantity'] * $item['unit_price'],
                    'image' => $imagePath,
                ]);
            }

            // Send email (use queue if available, or just send normally)
            try {
                $companySettings = CompanySetting::first();

            $logoData = null;

            $mailLogo = null;

            if ($companySettings && $companySettings->logo) {
                try {
                    // Clean the path
                    $cleanPath = str_replace('public/', '', $companySettings->logo);
                    $cleanPath = str_replace('storage/', '', $cleanPath);
                    $cleanPath = ltrim($cleanPath, '/');

                    // Get the logo from storage
                    if (Storage::disk('public')->exists($cleanPath)) {
                        $logoContents = Storage::disk('public')->get($cleanPath);
                        $mimeType = Storage::disk('public')->mimeType($cleanPath);
                        $logoData = 'data:' . $mimeType . ';base64,' . base64_encode($logoContents);
                    }
                } catch (\Exception $e) {
                    Log::warning("Failed to load logo: " . $e->getMessage());
                }
            }

            
            if ($companySettings && $companySettings->logo && Storage::disk('public')->exists($companySettings->logo)) {
                $mailLogo = Storage::disk('public')->url($companySettings->logo);
            }

                $company = [
                    "company_name" => $companySettings->company_name ?? "My Company",
                    "company_address" => $companySettings->company_address ?? "",
                    "company_email" => $companySettings->company_email ?? "",
                    "company_phone" => $companySettings->company_phone ?? "",
                    "logo" => $logoData,
                    "mail_logo" => $mailLogo,
                    "invoice_footer" => $companySettings->invoice_footer ?? "",
                    "company_tagline" => $companySettings->company_tagline ?? "",
                    "invoice_notes" => $companySettings->invoice_notes ?? "",
                ];

                $currency = $companySettings->currency ?? "GHS";

                // 🔥 FIX 3: Don't queue - send normally or skip if slow
                Mail::to($invoice->customer->email)->send(new InvoiceMail($invoice, $company, $currency));
                Log::info("Invoice email sent successfully to " . $invoice->customer->email);
            } catch (\Exception $e) {
                Log::error('Failed to send invoice email: ' . $e->getMessage());
                // Don't return error, invoice was created successfully
            }

            return response()->json([
                'message' => 'Invoice created successfully',
                'invoice_number' => $invoiceNumber,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating invoice: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'message' => 'Failed to create invoice: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete uploaded images from storage
     */
    private function deleteUploadedImages(array $imagePaths)
    {
        if (empty($imagePaths)) return;

        foreach ($imagePaths as $path) {
            try {
                if (file_exists($path)) {
                    unlink($path);
                    Log::info("Deleted image: {$path}");
                }
            } catch (\Exception $e) {
                Log::warning('Failed to delete image: ' . $path . ' - ' . $e->getMessage());
            }
        }
    }

    public function update(Request $request, string $invoice_number)
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
            'items.*.image'       => 'nullable|image|mimes:jpeg,jpg,png,gif,webp,avif,bmp,svg,ico,tiff,tif,heic,heif|max:5120',
            'tax_percent' => 'nullable|numeric|min:0|max:100',
        ], [
            // Customer validation messages
            'customer_id.exists' => 'The selected customer does not exist.',

            // New customer validation messages
            'new_customer.array' => 'New customer data must be provided as an array.',
            'new_customer.name.required_without' => 'Customer name is required when not selecting an existing customer.',
            'new_customer.name.string' => 'Customer name must be a valid text.',
            'new_customer.name.max' => 'Customer name cannot exceed 255 characters.',
            'new_customer.email.required_without' => 'Customer email is required when not selecting an existing customer.',
            'new_customer.email.email' => 'Please enter a valid email address.',
            'new_customer.email.max' => 'Email cannot exceed 255 characters.',
            'new_customer.address.required_without' => 'Customer address is required when not selecting an existing customer.',
            'new_customer.address.string' => 'Address must be a valid text.',
            'new_customer.address.max' => 'Address cannot exceed 500 characters.',
            'new_customer.phone.required_without' => 'Customer phone number is required when not selecting an existing customer.',
            'new_customer.phone.string' => 'Phone number must be a valid text.',
            'new_customer.phone.max' => 'Phone number cannot exceed 20 characters.',

            // Invoice date validation messages
            'invoice_date.required' => 'Invoice date is required.',
            'invoice_date.date' => 'Invoice date must be a valid date.',

            // Due date validation messages
            'due_date.required' => 'Due date is required.',
            'due_date.date' => 'Due date must be a valid date.',
            'due_date.after_or_equal' => 'Due date cannot be earlier than the invoice date.',

            // Status validation messages
            'status.required' => 'Invoice status is required.',
            'status.in' => 'Status must be either Paid, Pending, or Overdue.',

            // Notes validation messages
            'notes.string' => 'Notes must be valid text.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',

            // Items array validation messages
            'items.required' => 'At least one invoice item is required.',
            'items.array' => 'Items must be provided as an array.',
            'items.min' => 'Please add at least one item to the invoice.',

            // Item ID validation messages
            'items.*.id.exists' => 'One of the invoice items could not be found.',

            // Item description validation messages
            'items.*.description.required' => 'Item description is required.',
            'items.*.description.string' => 'Item description must be valid text.',
            'items.*.description.max' => 'Item description cannot exceed 255 characters.',

            // Item quantity validation messages
            'items.*.quantity.required' => 'Item quantity is required.',
            'items.*.quantity.numeric' => 'Item quantity must be a valid number.',
            'items.*.quantity.min' => 'Item quantity must be at least 1.',

            // Item unit price validation messages
            'items.*.unit_price.required' => 'Item unit price is required.',
            'items.*.unit_price.numeric' => 'Item unit price must be a valid number.',
            'items.*.unit_price.min' => 'Item unit price cannot be negative.',

            // Item image validation messages
            'items.*.image.image' => 'The uploaded file must be an image.',
            'items.*.image.mimes' => 'The image must be a file of type: JPEG, JPG, PNG, GIF, WebP, AVIF, BMP, SVG, ICO, TIFF, HEIC, or HEIF.',
            'items.*.image.max' => 'The image size must not exceed 5MB.',

            // Tax validation messages
            'tax_percent.numeric' => 'Tax percent must be a valid number.',
            'tax_percent.min' => 'Tax percent cannot be negative.',
            'tax_percent.max' => 'Tax percent cannot exceed 100%.',
        ]);

        DB::beginTransaction();

        $user = Auth::user();

        if (!$user->can_create_invoice) {
            return response()->json([
                'message' => "Sorry you're not allowed to update an invoice"
            ], 403);
        }

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

            // Track uploaded images for rollback
            $uploadedImages = [];
            $oldImagesToDelete = [];

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

            if ($invoice->isDirty()) {
                $invoice->save();
                $hasChanges = true;
            }

            // ---------------- ITEMS UPDATE WITH IMAGES ----------------
            $existingItemIds = $invoice->items->pluck('id')->toArray();
            $incomingItemIds = collect($request->items)->pluck('id')->filter()->toArray();

            // Delete removed items and their images
            $itemsToDelete = array_diff($existingItemIds, $incomingItemIds);

            if (!empty($itemsToDelete)) {
                $deletedItems = $invoice->items()->whereIn('id', $itemsToDelete)->get();
                foreach ($deletedItems as $deletedItem) {
                    if ($deletedItem->image) {
                        // Remove the 'storage/' prefix to get the relative path
                        $relativePath = str_replace('storage/', '', $deletedItem->image);
                        if (Storage::disk('public')->exists($relativePath)) {
                            Storage::disk('public')->delete($relativePath);
                        }
                    }
                }
                $invoice->items()->whereIn('id', $itemsToDelete)->delete();
                $hasChanges = true;
            }

            // Update or Create items with images
            foreach ($request->items as $index => $itemData) {
                $lineTotal = round($itemData['quantity'] * $itemData['unit_price'], 2);
                $imagePath = null;

                // Handle image upload for this item
                if (isset($itemData['image']) && $itemData['image'] instanceof \Illuminate\Http\UploadedFile) {
                    try {
                        // Generate unique filename
                        $filename = 'inv_item_' . $invoice->id . '_' . $index . '_' . uniqid() . '.' . $itemData['image']->getClientOriginalExtension();

                        // Store using Laravel Storage
                        $storedPath = Storage::disk('public')->putFileAs('invoice-items', $itemData['image'], $filename);
                        $imagePath = 'storage/' . $storedPath;

                        // Store for rollback
                        $uploadedImages[] = Storage::disk('public')->path($storedPath);
                    } catch (\Exception $e) {
                        $this->deleteUploadedImages($uploadedImages);
                        throw new \Exception('Failed to upload image for item ' . ($index + 1) . ': ' . $e->getMessage());
                    }
                }

                if (!empty($itemData['id'])) {
                    $item = $invoice->items()->where('id', $itemData['id'])->first();

                    if ($item) {
                        // Store old image path if we're replacing it
                        $oldImagePath = null;
                        if ($imagePath && $item->image) {
                            $oldImagePath = $item->image;
                            $oldImagesToDelete[] = $oldImagePath;
                        }

                        $item->description = $itemData['description'];
                        $item->quantity    = $itemData['quantity'];
                        $item->unit_price  = round($itemData['unit_price'], 2);
                        $item->total       = $lineTotal;

                        if ($imagePath) {
                            $item->image = $imagePath;
                        }

                        if ($item->isDirty()) {
                            $item->save();
                            $hasChanges = true;

                            // Delete old image after successful save
                            if ($oldImagePath) {
                                $relativePath = str_replace('storage/', '', $oldImagePath);
                                if (Storage::disk('public')->exists($relativePath)) {
                                    Storage::disk('public')->delete($relativePath);
                                }
                            }
                        }
                    }
                } else {
                    // Create new item with image
                    $invoice->items()->create([
                        'description' => $itemData['description'],
                        'quantity'    => $itemData['quantity'],
                        'unit_price'  => round($itemData['unit_price'], 2),
                        'total'       => $lineTotal,
                        'image'       => $imagePath,
                    ]);
                    $hasChanges = true;
                }
            }

            // If nothing changed
            if (!$hasChanges) {
                $this->deleteUploadedImages($uploadedImages);
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

            if (isset($uploadedImages)) {
                $this->deleteUploadedImages($uploadedImages);
            }

            Log::error("Invoice update error: " . $e->getMessage());

            return response()->json([
                "message" => "Failed to update invoice: " . $e->getMessage()
            ], 500);
        }
    }

    public function view(string $invoice_number)
    {
        try {
            $invoice = Invoice::with(['customer', 'items'])
                ->where('invoice_number', $invoice_number)
                ->first();

            if (!$invoice) {
                return response()->json(["message" => "Invoice $invoice_number not found!"], 404);
            }

            return response()->json($invoice, 200);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    public function downloadPdf(string $invoice_number)
    {
        try {

            $user = Auth::user();

            if (!$user->can_download_pdf) {
                return response()->json([
                    'message' => "Sorry you're not allowed to download invoice"
                ], 403);
            }

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
                "logo" => $companySettings->logo
                    ? public_path("storage/" . $companySettings->logo)
                    : null,
                "company_tagline" => $companySettings->company_tagline ?? "",
                "invoice_footer" => $companySettings->invoice_footer ?? "",
                "invoice_notes" => $companySettings->invoice_notes ?? "",
            ];

            $currency = $companySettings->currency ?? "GHS";

            $pdf = Pdf::loadView("pdf.invoice", [
                "invoice" => $invoice,
                "company" => $company,
                "currency" => $currency,
            ])->setOptions([
                "isRemoteEnabled" => true,
                "isHtml5ParserEnabled" => true
            ]);

            return $pdf->download("invoice-" . $invoice->invoice_number . ".pdf");
        } catch (Exception $ex) {
            Log::error('Download Error: ' . $ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    public function sendInvoiceEmail(string $invoice_number)
    {
        try {
            ini_set('memory_limit', '512M');
            set_time_limit(120);

            $user = Auth::user();

            if (!$user->can_send_email) {
                return response()->json([
                    'message' => "Sorry you're not allowed to send invoice to email"
                ], 403);
            }

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

            $logoData = null;
            $mailLogo = null;
            if ($companySettings && $companySettings->logo) {
                try {
                    // Clean the path
                    $cleanPath = str_replace('public/', '', $companySettings->logo);
                    $cleanPath = str_replace('storage/', '', $cleanPath);
                    $cleanPath = ltrim($cleanPath, '/');

                    // Get the logo from storage
                    if (Storage::disk('public')->exists($cleanPath)) {
                        $logoContents = Storage::disk('public')->get($cleanPath);
                        $mimeType = Storage::disk('public')->mimeType($cleanPath);
                        $logoData = 'data:' . $mimeType . ';base64,' . base64_encode($logoContents);
                    }
                } catch (\Exception $e) {
                    Log::warning("Failed to load logo: " . $e->getMessage());
                }
            }

            
            if ($companySettings && $companySettings->logo && Storage::disk('public')->exists($companySettings->logo)) {
                $mailLogo = Storage::disk('public')->url($companySettings->logo);
            }

            $company = [
                "company_name" => $companySettings->company_name ?? "My Company",
                "company_address" => $companySettings->company_address ?? "",
                "company_email" => $companySettings->company_email ?? "",
                "company_phone" => $companySettings->company_phone ?? "",
                "logo" => $logoData, // Now contains base64 data
                "mail_logo" => $mailLogo, // URL for the logo in the email
                "invoice_footer" => $companySettings->invoice_footer ?? "",
                "company_tagline" => $companySettings->company_tagline ?? "",
                "invoice_notes" => $companySettings->invoice_notes ?? "",
            ];

            $currency = $companySettings?->currency ?? "GHS";

            // SEND MAIL 
            Mail::to($invoice->customer->email)
                ->send(new InvoiceMail($invoice, $company, $currency));

            // mark invoice as sent
            $invoice->sent_at = Carbon::now();
            $invoice->save();

            return response()->json([
                "message" => "Invoice sent successfully to " . $invoice->customer->email
            ], 200);
        } catch (Exception $ex) {
            Log::error("Send Invoice Error: " . $ex->getMessage());

            return response()->json([
                'message' => 'An unexpected error occurred'
            ], 500);
        }
    }

    public function markAsPaid(string $invoice_number)
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

    public function duplicateInvoice(string $invoice_number)
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

    public function voidInvoice(string $invoice_number)
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

    public function deleteInvoice(string $invoice_number)
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

    public function publicDownload(Request $request, string $invoice_number)
    {
        try {
            $token = $request->query("token");

            $invoice = Invoice::with(['customer', 'items'])
                ->where("invoice_number", $invoice_number)
                ->where("public_token", $token)
                ->first();

            if (!$invoice) {
                return response()->json([
                    "message" => "Invalid or expired invoice link"
                ], 403);
            }

            $companySettings = CompanySetting::first();

            $company = [
                "company_name" => $companySettings->company_name ?? "My Company",
                "company_address" => $companySettings->company_address ?? "",
                "company_email" => $companySettings->company_email ?? "",
                "company_phone" => $companySettings->company_phone ?? "",
                "logo" => $companySettings->logo
                    ? public_path("storage/" . $companySettings->logo)
                    : null,
                "company_tagline" => $companySettings->company_tagline ?? "",
                "invoice_footer" => $companySettings->invoice_footer ?? "",
                "invoice_notes" => $companySettings->invoice_notes ?? "",

            ];

            $currency = $companySettings->currency ?? "GHS";

            $pdf = Pdf::loadView("pdf.invoice", [
                "invoice" => $invoice,
                "company" => $company,
                "currency" => $currency,
            ])->setOptions([
                "isRemoteEnabled" => true,
                "isHtml5ParserEnabled" => true
            ]);

            return response($pdf->output(), 200)
                ->header("Content-Type", "application/pdf")
                ->header("Content-Disposition", "attachment; filename=invoice-{$invoice->invoice_number}.pdf");
        } catch (\Exception $ex) {
            Log::error("Public invoice download error: " . $ex->getMessage());

            return response()->json([
                "message" => "An unexpected error occurred"
            ], 500);
        }
    }
}
