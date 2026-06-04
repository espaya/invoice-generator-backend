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
            margin: 10px;
            padding: 0;
            position: relative;
        }

        /* Watermark Styles */
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.1;
            z-index: -1;
            pointer-events: none;
            text-align: center;
        }

        .watermark img {
            width: 400px;
            height: auto;
            opacity: 0.15;
        }

        /* Alternative: Diagonal watermark */
        .watermark-diagonal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .watermark-diagonal img {
            width: 500px;
            height: auto;
            opacity: 0.1;
            transform: rotate(-25deg);
        }

        /* Optional: Text watermark behind logo */
        .watermark-text {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-25deg);
            font-size: 80px;
            color: #000;
            opacity: 0.08;
            white-space: nowrap;
            z-index: -1;
            font-weight: bold;
            letter-spacing: 5px;
        }

        /* Optional: Repeating watermark pattern */
        .watermark-pattern {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
            background-repeat: repeat;
            background-size: 150px;
            opacity: 0.05;
        }

        .top-header {
            width: 100%;
            margin-bottom: 20px;
        }

        .top-header td {
            vertical-align: top;
        }

        .title {
            font-size: 28px;
            font-weight: bold;
            margin: 0 0 10px 0;
        }

        .invoice-details p {
            margin: 2px 0;
            line-height: 1.4;
        }

        .company-details {
            text-align: right;
            font-size: 13px;
            line-height: 1.4;
        }

        .company-details strong {
            font-size: 14px;
        }

        .billto {
            margin-top: 15px;
            margin-bottom: 10px;
        }

        .billto strong {
            display: block;
            margin-bottom: 3px;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            font-size: 12px;
        }

        table.items th {
            text-align: left;
            border: 1px solid #000;
            padding: 6px;
            font-weight: bold;
            background: #fff;
        }

        table.items td {
            border: 1px solid #000;
            padding: 6px;
        }

        .right {
            text-align: right;
        }

        .notes {
            margin-top: 12px;
            font-size: 12px;
        }

        .notes strong {
            display: block;
            margin-bottom: 3px;
        }

        .totals-wrapper {
            width: 100%;
            margin-top: 15px;
        }

        .totals-table {
            width: 260px;
            margin-left: auto;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 4px 0;
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
            padding-top: 6px;
        }

        .footer {
            text-align: center;
            margin-top: 40px;
            font-size: 12px;
            color: #555;
        }

        hr {
            border: none;
            border-top: 1px solid #ddd;
            margin: 15px 0;
        }

        /* Print optimization for watermark */
        @media print {
            .watermark,
            .watermark-diagonal,
            .watermark-pattern {
                position: fixed;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>

    <!-- OPTION 1: Centered Logo Watermark (Recommended) -->
    <div class="watermark">
        @if(!empty($company['logo']))
            <img src="{{ $company['logo'] }}" alt="Watermark">
        @else
            <div style="font-size: 60px; font-weight: bold; opacity: 0.1;">{{ $company['company_name'] ?? 'COMPANY' }}</div>
        @endif
    </div>

    <!-- OPTION 2: Diagonal Logo Watermark (Uncomment to use instead of Option 1) -->
    <!--
    <div class="watermark-diagonal">
        @if(!empty($company['logo']))
            <img src="{{ $company['logo'] }}" alt="Watermark">
        @endif
    </div>
    -->

    <!-- OPTION 3: Text + Logo Watermark (Uncomment to use instead) -->
    <!--
    <div class="watermark-text">
        {{ strtoupper($company['company_name'] ?? 'INVOICE') }}
    </div>
    <div class="watermark">
        @if(!empty($company['logo']))
            <img src="{{ $company['logo'] }}" alt="Watermark" style="margin-top: 80px;">
        @endif
    </div>
    -->

    <!-- OPTION 4: Repeating Pattern Watermark (Uncomment to use instead) -->
    <!--
    <div class="watermark-pattern" style="background-image: url('{{ $company['logo'] ?? '' }}');">
    </div>
    -->

    <!-- HEADER -->
    <table class="top-header">
        <tr>
            <td>
                <h1 class="title">Invoice</h1>

                <div class="invoice-details">
                    <p>Invoice ID: <strong>{{ $invoice->invoice_number }}</strong></p>
                    <p><strong>Invoice Date:</strong> {{ \Carbon\Carbon::parse($invoice->invoice_date)->format('M j, Y') }}</p>
                    <p><strong>Due Date:</strong> {{ \Carbon\Carbon::parse($invoice->due_date)->format('M j, Y') }}</p>
                    <p>Status: <strong>{{ strtoupper($invoice->status) }}</strong></p>
                </div>
            </td>

            <td class="company-details">
                <div style="text-align:right;">
                    <img
                        src="{{ $company['logo'] }}"
                        width="80"
                        height="80"
                        style="margin-bottom:4px; display:inline-block;">
                    <br>

                    <strong>{!! $company['company_name'] ?? 'Company Name' !!}</strong><br>
                    {!! $company['company_tagline'] ?? '' !!}<br>
                    {!! $company['company_address'] ?? '' !!}<br>
                    {!! $company['company_email'] ?? '' !!}<br>
                    {!! $company['company_phone'] ?? '' !!}
                </div>
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
                <th class="right" style="width: 70px;">Quantity</th>
                <th class="right" style="width: 100px;">Unit Price</th>
                <th class="right" style="width: 100px;">Total</th>
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
        {{ $company['invoice_footer'] ?? 'Thank you!' }}
    </div>

</body>

</html>