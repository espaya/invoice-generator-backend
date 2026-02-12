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

        .download-btn {
            display: inline-block;
            background: #0d6efd;
            color: #fff;
            padding: 12px 18px;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
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
        }

        table.items th {
            font-weight: bold;
            background: #fff;
            text-align: left;
        }

        .right {
            text-align: right;
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
                    Please find your invoice below. A PDF copy is also attached to this email.
                </p>

                <!-- Download Button -->
                <div style="margin:20px 0;">
                    <a href="{{ $downloadUrl }}" class="download-btn">
                        Download Invoice PDF
                    </a>
                </div>
            </div>

            <hr class="divider">

            <!-- Invoice Header -->
            <table class="invoice-header">
                <tr>
                    <td>
                        <h1 class="invoice-title">Invoice</h1>

                        <div class="invoice-details">
                            <p><strong>Invoice ID:</strong> {{ $invoice->invoice_number }}</p>
                            <p><strong>Invoice Date:</strong> {{ \Carbon\Carbon::parse($invoice->invoice_date)->format('jS F, Y') }}</p>
                            <p><strong>Due Date:</strong> {{ \Carbon\Carbon::parse($invoice->due_date)->format('jS F, Y') }}</p>
                            <p><strong>Status:</strong> {{ strtoupper($invoice->status) }}</p>
                        </div>
                    </td>

                    <td class="company-details">
                        <strong>{{ $company['company_name'] ?? "Company Name" }}</strong><br>
                        {{ $company['company_address'] ?? "" }}<br>
                        {{ $company['company_email'] ?? "" }}<br>
                        {{ $company['company_phone'] ?? "" }}
                    </td>
                </tr>
            </table>

            <!-- Bill To -->
            <div class="billto">
                <strong>Bill To:</strong>
                {{ $invoice->customer->name }} ({{ $invoice->customer->email }})<br>
                {{ $invoice->customer->address }}
            </div>

            <!-- Items Table -->
            <table class="items">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="right" style="width: 90px;">Quantity</th>
                        <th class="right" style="width: 140px;">Unit Price</th>
                        <th class="right" style="width: 140px;">Total</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($invoice->items as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td class="right">{{ $item->quantity }}</td>
                        <td class="right">{{ $currency }}{{ number_format($item->unit_price, 2) }}</td>
                        <td class="right">{{ $currency }}{{ number_format($item->total, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Notes -->
            <div class="notes">
                <strong>Notes:</strong>
                {{ $invoice->notes ?? "No notes provided." }}
            </div>

            <!-- Totals -->
            <div class="totals-wrapper">
                <table class="totals-table">
                    <tr>
                        <td class="label">Subtotal:</td>
                        <td class="value">{{ $currency }}{{ number_format($invoice->subtotal, 2) }}</td>
                    </tr>

                    <tr>
                        <td class="label">
                            Tax ({{ number_format($invoice->tax_percent * 100, 0) }}%):
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
                Thank you for your business!
            </div>

        </div>
    </div>
</body>

</html>