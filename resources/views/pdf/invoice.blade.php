<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>

    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
            font-size: 12px;
        }

        .container {
            width: 100%;
            max-width: 900px;
            margin: auto;
            background: #fff;
        }

        .invoice-header {
            width: 100%;
            margin-bottom: 20px;
        }

        .invoice-header td {
            vertical-align: top;
        }

        .invoice-title {
            font-size: 24px;
            font-weight: bold;
            margin: 0 0 10px 0;
            color: #111;
        }

        .invoice-details p {
            margin: 3px 0;
            font-size: 11px;
        }

        .company-details {
            text-align: right;
            font-size: 11px;
        }

        .company-logo {
            max-width: 100px;
            max-height: 80px;
            margin-bottom: 8px;
        }

        .billto {
            margin-bottom: 20px;
            font-size: 11px;
        }

        .billto strong {
            display: block;
            margin-bottom: 5px;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        table.items th,
        table.items td {
            border: 1px solid #000;
            padding: 6px;
            vertical-align: top;
        }

        table.items th {
            font-weight: bold;
            background: #f5f5f5;
            text-align: left;
        }

        .item-image {
            max-width: 50px;
            max-height: 50px;
            object-fit: cover;
        }

        .no-image {
            width: 50px;
            height: 50px;
            background-color: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 9px;
            color: #999;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .notes {
            margin-top: 15px;
            font-size: 11px;
        }

        .notes strong {
            display: block;
            margin-bottom: 5px;
        }

        .totals-wrapper {
            margin-top: 20px;
            width: 100%;
        }

        .totals-table {
            width: 280px;
            margin-left: auto;
            border-collapse: collapse;
            font-size: 11px;
        }

        .totals-table td {
            padding: 4px 0;
        }

        .totals-table .label {
            text-align: right;
            padding-right: 10px;
        }

        .totals-table .value {
            text-align: right;
            width: 120px;
        }

        .grand-total {
            font-size: 14px;
            font-weight: bold;
            padding-top: 8px;
        }

        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 10px;
            color: #666;
        }

        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
        }

        .status-paid {
            background-color: #28a745;
            color: white;
        }

        .status-pending {
            background-color: #ffc107;
            color: #333;
        }

        .status-overdue {
            background-color: #dc3545;
            color: white;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Invoice Header -->
        <table class="invoice-header">
            <tr>
                <td>
                    <h1 class="invoice-title">INVOICE</h1>

                    <div class="invoice-details">
                        <p><strong>Invoice Number:</strong> {{ $invoice->invoice_number }}</p>
                        <p><strong>Invoice Date:</strong> {{ \Carbon\Carbon::parse($invoice->invoice_date)->format('M d, Y') }}</p>
                        <p><strong>Due Date:</strong> {{ \Carbon\Carbon::parse($invoice->due_date)->format('M d, Y') }}</p>
                        <p>
                            <strong>Status:</strong>
                            <span class="status-badge status-{{ $invoice->status }}">
                                {{ strtoupper($invoice->status) }}
                            </span>
                        </p>
                    </div>
                </td>

                <td class="company-details">

                    @if(!empty($company['logo']))
                    <img src="{{ $company['logo'] }}" class="company-logo" alt="Logo">
                    <br>
                    @endif

                    <strong>{!! $company['company_name'] ?? 'Company Name' !!}</strong><br>
                    {!! $company['company_tagline'] ?? '' !!}<br>
                    {!! $company['company_address'] ?? '' !!}<br>
                    {!! $company['company_email'] ?? '' !!}<br>
                    {!! $company['company_phone'] ?? '' !!}
                </td>
            </tr>
        </table>

        <!-- Bill To -->
        <div class="billto">
            <strong>Bill To:</strong>
            {{ $invoice->customer->name }} ({{ $invoice->customer->email }})<br>
            {{ $invoice->customer->address }}
            @if(!empty($invoice->customer->phone))
            <br>Phone: {{ $invoice->customer->phone }}
            @endif
        </div>

        <!-- Items Table with Images -->
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 70px;">Image</th>
                    <th>Description</th>
                    <th class="right" style="width: 60px;">Qty</th>
                    <th class="right" style="width: 100px;">Unit Price</th>
                    <th class="right" style="width: 100px;">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoice->items as $item)
                <tr>
                    <td class="center">
                        @php
                        $imageData = null;
                        $imagePath = $item->image ?? '';

                        if (!empty($imagePath)) {
                        // Check if it's a URL
                        if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
                        try {
                        $imageContents = @file_get_contents($imagePath);
                        if ($imageContents !== false) {
                        $imageData = 'data:image/' . pathinfo($imagePath, PATHINFO_EXTENSION) . ';base64,' . base64_encode($imageContents);
                        }
                        } catch (\Exception $e) {
                        // Silent fail
                        }
                        } else {
                        // Handle storage path
                        $cleanPath = str_replace('/storage/', '', $imagePath);
                        $cleanPath = str_replace('storage/', '', $cleanPath);

                        // Try multiple paths
                        $possiblePaths = [
                        storage_path('app/public/' . $cleanPath),
                        public_path('storage/' . $cleanPath),
                        public_path($imagePath),
                        ];

                        foreach ($possiblePaths as $path) {
                        if (file_exists($path) && is_file($path)) {
                        try {
                        $imageContents = @file_get_contents($path);
                        if ($imageContents !== false) {
                        $extension = pathinfo($path, PATHINFO_EXTENSION);
                        $imageData = 'data:image/' . $extension . ';base64,' . base64_encode($imageContents);
                        break;
                        }
                        } catch (\Exception $e) {
                        // Silent fail
                        }
                        }
                        }
                        }
                        }
                        @endphp

                        @if(!empty($imageData))
                        <img src="{{ $imageData }}" class="item-image" alt="{{ $item->description }}" style="max-width: 60px; max-height: 60px; object-fit: cover;" />
                        @else
                        <div class="no-image" style="width: 60px; height: 60px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #999;">
                            No Image
                        </div>
                        @endif
                    </td>
                    <td>{{ $item->description }}</td>
                    <td class="right">{{ $item->quantity }}</td>
                    <td class="right">{{ $currency }}{{ number_format($item->unit_price, 2) }}</td>
                    <td class="right">{{ $currency }}{{ number_format($item->total, 2) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="center">No items found</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Notes -->
        @if($company['invoice_notes'] ?? false)
        <div class="notes">
            <strong>Notes:</strong>
            {!! $company['invoice_notes'] !!}
        </div>
        @endif

        <!-- Totals -->
        <div class="totals-wrapper">
            <table class="totals-table">
                <tr>
                    <td class="label">Subtotal:</td>
                    <td class="value">{{ $currency }}{{ number_format($invoice->subtotal, 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Tax ({{ number_format($invoice->tax_percent, 0) }}%):</td>
                    <td class="value">{{ $currency }}{{ number_format($invoice->tax_amount, 2) }}</td>
                </tr>
                <tr>
                    <td class="label grand-total">Total:</td>
                    <td class="value grand-total">{{ $currency }}{{ number_format($invoice->total, 2) }}</td>
                </tr>
            </table>
        </div>

        <!-- Footer -->
        <div class="footer">
            {{ $company['invoice_footer'] ?? 'Thank you for your business!' }}
        </div>
    </div>
</body>

</html>