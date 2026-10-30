<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Delivery Order - {{ $order->do_number }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333;
            font-size: 12px;
            line-height: 1.4;
            padding: 20px;
        }
        .header {
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .company-name {
            font-size: 18px;
            font-weight: bold;
            color: #1a365d;
        }
        .document-title {
            font-size: 16px;
            font-weight: bold;
            text-align: right;
            text-transform: uppercase;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 20px;
        }
        .meta-table td {
            vertical-align: top;
            padding: 3px 0;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .items-table th, .items-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .items-table th {
            background-color: #f7fafc;
            font-weight: bold;
        }
        .signatures {
            width: 100%;
            margin-top: 40px;
        }
        .signature-box {
            text-align: center;
            width: 33%;
        }
        .signature-line {
            margin-top: 60px;
            border-top: 1px solid #333;
            width: 80%;
            margin-left: auto;
            margin-right: auto;
        }
    </style>
</head>
<body>
    <div class="header">
        <table style="width: 100%;">
            <tr>
                <td>
                    <div class="company-name">SMART INJECTION MANUFACTURING LTD</div>
                    <div>Discrete Automotive Plastics Division</div>
                    <div>Cikarang Industrial Estate Block B-12, West Java</div>
                </td>
                <td style="text-align: right;">
                    <div class="document-title">DELIVERY ORDER</div>
                    <div><strong>No:</strong> {{ $order->do_number }}</div>
                    <div><strong>Date:</strong> {{ $order->dispatched_at ? $order->dispatched_at->format('d/m/Y H:i') : now()->format('d/m/Y') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="meta-table">
        <tr>
            <td style="width: 50%;">
                <strong>Consignee / Customer:</strong><br>
                {{ $order->customer_name }}<br>
                {{ $order->shipping_address }}
            </td>
            <td style="width: 50%;">
                <strong>Carrier / Logistics Details:</strong><br>
                <strong>Vehicle Plate No:</strong> {{ $order->vehicle_plate_number }}<br>
                <strong>Driver Name:</strong> {{ $order->driver_name }}<br>
                <strong>Total Cartons:</strong> {{ $order->total_cartons }} Carton(s)
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 25%;">Part SKU</th>
                <th style="width: 35%;">Item Description</th>
                <th style="width: 20%;">Lot Number</th>
                <th style="width: 15%; text-align: right;">Quantity Shipped</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td><strong>{{ $item->item->sku }}</strong></td>
                <td>{{ $item->item->name }}</td>
                <td>{{ $item->lot?->lot_number ?? '-' }}</td>
                <td style="text-align: right;">{{ number_format($item->quantity_loaded, 0) }} {{ $item->item->unit_of_measure }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="signatures">
        <tr>
            <td class="signature-box">
                Shipper / Warehouse<br>
                <div class="signature-line">( Warehouse Officer )</div>
            </td>
            <td class="signature-box">
                Carrier / Logistics<br>
                <div class="signature-line">( {{ $order->driver_name }} )</div>
            </td>
            <td class="signature-box">
                Recipient / Consignee<br>
                <div class="signature-line">( Signature & Company Stamp )</div>
            </td>
        </tr>
    </table>
</body>
</html>
