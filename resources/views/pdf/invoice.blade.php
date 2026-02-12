<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 13px;
            color: #222;
            margin: 40px;
        }

        .top-header {
            width: 100%;
            margin-bottom: 30px;
        }

        .top-header td {
            vertical-align: top;
        }

        .title {
            font-size: 28px;
            font-weight: bold;
            margin: 0;
        }

        .invoice-details p {
            margin: 4px 0;
            line-height: 1.5;
        }

        .company-details {
            text-align: right;
            font-size: 13px;
            line-height: 1.6;
        }

        .company-details strong {
            font-size: 14px;
        }

        .billto {
            margin-top: 20px;
            margin-bottom: 15px;
        }

        .billto strong {
            display: block;
            margin-bottom: 5px;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 13px;
        }

        table.items th {
            text-align: left;
            border: 1px solid #000;
            padding: 8px;
            font-weight: bold;
            background: #fff;
        }

        table.items td {
            border: 1px solid #000;
            padding: 8px;
        }

        .right {
            text-align: right;
        }

        .notes {
            margin-top: 15px;
            font-size: 12px;
        }

        .notes strong {
            display: block;
            margin-bottom: 4px;
        }

        .totals-wrapper {
            width: 100%;
            margin-top: 20px;
        }

        .totals-table {
            width: 260px;
            margin-left: auto;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 6px 0;
            font-size: 13px;
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
            font-size: 16px;
            font-weight: bold;
            padding-top: 10px;
        }

        .footer {
            text-align: center;
            margin-top: 60px;
            font-size: 12px;
            color: #555;
        }

        hr {
            border: none;
            border-top: 1px solid #ddd;
            margin: 20px 0;
        }
    </style>
</head>

<body>

    <!-- HEADER -->
    <table class="top-header">
        <tr>
            <td>
                <h1 class="title">Invoice</h1>

                <div class="invoice-details">
                    <p>Invoice ID: <strong>{{ $invoice->invoice_number }}</strong></p>
                    <p>Invoice Date: {{ \Carbon\Carbon::parse($invoice->invoice_date)->format('jS F, Y') }}</p>
                    <p>Due Date: {{ \Carbon\Carbon::parse($invoice->due_date)->format('jS F, Y') }}</p>
                    <p>Status: <strong>{{ strtoupper($invoice->status) }}</strong></p>
                </div>
            </td>

            <td class="company-details">
                {{-- Logo (optional) --}}
                @if(!empty($company['logo']))
                    <img src="{{ $company['logo'] }}" width="120" style="margin-bottom:10px;">
                @endif

                <strong>{{ $company['company_name'] ?? 'Company Name' }}</strong><br>
                {{ $company['company_address'] ?? '' }}<br>
                {{ $company['company_email'] ?? '' }}<br>
                {{ $company['company_phone'] ?? '' }}
            </td>
        </tr>
    </table>

    <!-- BILL TO -->
    <div class="billto">
        <strong>Bill To:</strong>
        {{ $invoice->customer->name ?? 'N/A' }} ({{ $invoice->customer->email ?? '' }})<br>
        {{ $invoice->customer->address ?? '' }}
    </div>

    <!-- ITEMS TABLE -->
    <table class="items">
        <thead>
            <tr>
                <th>Description</th>
                <th class="right" style="width: 80px;">Quantity</th>
                <th class="right" style="width: 120px;">Unit Price</th>
                <th class="right" style="width: 120px;">Total</th>
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

    <!-- NOTES -->
    @if($invoice->notes)
        <div class="notes">
            <strong>Notes:</strong>
            {{ $invoice->notes }}
        </div>
    @endif

    <!-- TOTALS -->
    <div class="totals-wrapper">
        <table class="totals-table">
            <tr>
                <td class="label">Subtotal:</td>
                <td class="value">{{ $currency }}{{ number_format($invoice->subtotal, 2) }}</td>
            </tr>

            <tr>
                <td class="label">Tax ({{ number_format($invoice->tax_percent * 100, 0) }}%):</td>
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

    <!-- FOOTER -->
    <div class="footer">
        Thank you for your business!
    </div>

</body>
</html>
