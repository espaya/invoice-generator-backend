<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>

    <style>
        body {
            margin: 0;
            padding: 0;
            background: #f6f6f6;
            font-family: Arial, sans-serif;
        }

        .wrapper {
            width: 100%;
            padding: 30px 0;
            background: #f6f6f6;
        }

        .container {
            width: 850px;
            margin: auto;
            background: #fff;
            padding: 35px;
            border-radius: 6px;
        }

        .greeting h2 {
            margin-top: 0;
            font-size: 20px;
            font-weight: bold;
            color: #111;
        }

        .greeting p {
            font-size: 14px;
            color: #333;
            margin-bottom: 15px;
        }

        .divider {
            border: none;
            border-top: 1px solid #ddd;
            margin: 25px 0;
        }

        .invoice-header {
            width: 100%;
            margin-bottom: 25px;
        }

        .invoice-header td {
            vertical-align: top;
        }

        .invoice-title {
            font-size: 28px;
            font-weight: bold;
            margin: 0;
            color: #111;
        }

        .invoice-details p {
            margin: 5px 0;
            font-size: 13px;
            color: #333;
            line-height: 18px;
        }

        .company-details {
            text-align: right;
            font-size: 13px;
            color: #333;
            line-height: 18px;
        }

        .company-details strong {
            font-size: 14px;
        }

        .billto {
            margin-bottom: 20px;
            font-size: 13px;
            color: #333;
            line-height: 18px;
        }

        .billto strong {
            display: block;
            margin-bottom: 6px;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            color: #111;
        }

        table.items th,
        table.items td {
            border: 1px solid #000;
            padding: 8px;
            vertical-align: top;
        }

        table.items th {
            font-weight: bold;
            background: #fff;
            text-align: left;
        }

        .item-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }

        .no-image {
            width: 60px;
            height: 60px;
            background-color: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: #999;
            border-radius: 4px;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .notes {
            margin-top: 12px;
            font-size: 13px;
            color: #333;
            line-height: 18px;
        }

        .notes strong {
            display: block;
            margin-bottom: 6px;
        }

        .totals-wrapper {
            margin-top: 25px;
            width: 100%;
        }

        .totals-table {
            width: 300px;
            margin-left: auto;
            border-collapse: collapse;
            font-size: 13px;
            color: #333;
        }

        .totals-table td {
            padding: 5px 0;
        }

        .totals-table .label {
            text-align: right;
            padding-right: 10px;
        }

        .totals-table .value {
            text-align: right;
            width: 140px;
        }

        .grand-total {
            font-size: 16px;
            font-weight: bold;
            padding-top: 10px;
        }

        .footer {
            margin-top: 60px;
            text-align: center;
            font-size: 13px;
            color: #555;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <div class="container">

            <!-- Greeting -->
            <div class="greeting">
                <h2>Hello {{ $invoice->customer->name }},</h2>
                <p>
                    Please find a copy of your invoice attached below to this email.
                </p>
            </div>

            <hr class="divider">

            <!-- Invoice Header -->
            <table class="invoice-header">
                <tr>
                    <td>
                        <h1 class="invoice-title">Invoice</h1>

                        <div class="invoice-details">
                            <p><strong>Invoice ID:</strong> {{ $invoice->invoice_number }}</p>
                            <p><strong>Invoice Date:</strong> {{ \Carbon\Carbon::parse($invoice->invoice_date)->format('M j, Y') }}</p>
                            <p><strong>Due Date:</strong> {{ \Carbon\Carbon::parse($invoice->due_date)->format('M j, Y') }}</p>
                            <p>
                                <strong>Status:</strong>
                                <span style="
                                    @if($invoice->status == 'paid') color: #28a745; 
                                    @elseif($invoice->status == 'pending') color: #ffc107; 
                                    @else color: #dc3545; 
                                    @endif
                                    font-weight: bold;
                                ">
                                    {{ strtoupper($invoice->status) }}
                                </span>
                            </p>
                        </div>
                    </td>

                    <td class="company-details">
                        <div style="text-align:right;">
                            @if(!empty($company['logo']))
                            <img src="/storage/{{ $company['logo'] }}" class="company-logo" alt="Logo">
                            <br>
                            @endif

                            <strong>{!! $company['company_name'] ?? 'Company Name' !!}</strong><br>
                            {!! $company['company_tagline'] ?? '' !!}<br>
                            {!! $company['company_address'] ?? '' !!}<br>
                            {!! $company['company_email'] ?? '' !!}<br>
                            {!! $company['company_phone'] ?? '' !!}
                        </div>
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
                        <th style="width: 80px;">Image</th>
                        <th>Description</th>
                        <th class="right" style="width: 80px;">Qty</th>
                        <th class="right" style="width: 120px;">Unit Price</th>
                        <th class="right" style="width: 120px;">Total</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($invoice->items as $item)
                    <tr>
                        <td class="center">
                            @php
                            $imageUrl = $item->image ?? null;
                            if (!empty($imageUrl) && !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                            $imageUrl = url($imageUrl);
                            }
                            @endphp

                            @if(!empty($imageUrl))
                            <img src="{{ $imageUrl }}" class="item-image" alt="{{ $item->description }}" style="max-width: 60px; max-height: 60px; object-fit: cover; border-radius: 4px;" />
                            @else
                            <div class="no-image" style="width: 60px; height: 60px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #999; border-radius: 4px;">
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
                        <td class="label">
                            Tax ({{ number_format($invoice->tax_percent, 0) }}%):
                        </td>
                        <td class="value">
                            {{ $currency }}{{ number_format($invoice->total - $invoice->subtotal, 2) }}
                        </td>
                    </tr>

                    <tr>
                        <td class="label grand-total">Total:</td>
                        <td class="value grand-total">
                            {{ $currency }}{{ number_format($invoice->total, 2) }}
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Footer -->
            <div class="footer">
                {{ $company['invoice_footer'] ?? 'Thank you for your business!' }}
            </div>

        </div>
    </div>
</body>

</html>