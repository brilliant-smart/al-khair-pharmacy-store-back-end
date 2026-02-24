<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Purchase Order - {{ $po->po_number }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            margin: 0;
            padding: 20px;
            font-size: 12px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #2563eb;
        }
        .po-number {
            font-size: 18px;
            font-weight: bold;
            margin-top: 10px;
        }
        .info-section {
            margin-bottom: 20px;
        }
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .info-row {
            display: table-row;
        }
        .info-label {
            display: table-cell;
            font-weight: bold;
            width: 30%;
            padding: 5px;
            background-color: #f3f4f6;
        }
        .info-value {
            display: table-cell;
            padding: 5px;
            width: 70%;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th {
            background-color: #2563eb;
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: bold;
        }
        td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        .text-right {
            text-align: right;
        }
        .total-section {
            float: right;
            width: 40%;
            margin-top: 20px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
        }
        .total-label {
            font-weight: bold;
        }
        .grand-total {
            border-top: 2px solid #333;
            font-size: 16px;
            font-weight: bold;
            padding-top: 10px;
            margin-top: 10px;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 5px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-pending { background-color: #fef3c7; color: #92400e; }
        .status-approved { background-color: #dbeafe; color: #1e40af; }
        .status-received { background-color: #d1fae5; color: #065f46; }
        .status-completed { background-color: #d1fae5; color: #065f46; }
        .status-cancelled { background-color: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">Al-Khair Pharmacy Store</div>
        <div class="po-number">{{ $po->po_number }}</div>
        <div>Purchase Order</div>
    </div>

    <div class="info-section">
        <h3>Order Information</h3>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Status</div>
                <div class="info-value">
                    <span class="status-badge status-{{ $po->status }}">{{ strtoupper($po->status) }}</span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Supplier</div>
                <div class="info-value">{{ $po->supplier->name ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Order Date</div>
                <div class="info-value">{{ \Carbon\Carbon::parse($po->order_date)->format('F d, Y') }}</div>
            </div>
            @if($po->expected_delivery_date)
            <div class="info-row">
                <div class="info-label">Expected Delivery</div>
                <div class="info-value">{{ \Carbon\Carbon::parse($po->expected_delivery_date)->format('F d, Y') }}</div>
            </div>
            @endif
            <div class="info-row">
                <div class="info-label">Payment Method</div>
                <div class="info-value">{{ strtoupper(str_replace('_', ' ', $po->payment_method ?? 'CREDIT')) }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Payment Status</div>
                <div class="info-value">{{ strtoupper($po->payment_status ?? 'UNPAID') }}</div>
            </div>
        </div>
    </div>

    <h3>Items</h3>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Product</th>
                <th class="text-right">Qty Ordered</th>
                <th class="text-right">Qty Received</th>
                <th class="text-right">Unit Cost (₦)</th>
                <th class="text-right">Total (₦)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($po->items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $item->product->name ?? 'Product #' . $item->product_id }}</td>
                <td class="text-right">{{ $item->quantity_ordered }} {{ $item->unit_type ?? 'pcs' }}</td>
                <td class="text-right">{{ $item->quantity_received ?? 0 }}</td>
                <td class="text-right">{{ number_format($item->unit_cost, 2) }}</td>
                <td class="text-right">{{ number_format($item->total_cost ?? ($item->quantity_ordered * $item->unit_cost), 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total-section">
        <div class="total-row">
            <span class="total-label">Subtotal:</span>
            <span>₦{{ number_format($po->subtotal, 2) }}</span>
        </div>
        @if($po->vat_amount > 0)
        <div class="total-row">
            <span class="total-label">Tax/VAT:</span>
            <span>₦{{ number_format($po->vat_amount, 2) }}</span>
        </div>
        @endif
        @if($po->shipping_cost > 0)
        <div class="total-row">
            <span class="total-label">Shipping:</span>
            <span>₦{{ number_format($po->shipping_cost, 2) }}</span>
        </div>
        @endif
        @if($po->discount_amount > 0)
        <div class="total-row">
            <span class="total-label">Discount:</span>
            <span>-₦{{ number_format($po->discount_amount, 2) }}</span>
        </div>
        @endif
        <div class="total-row grand-total">
            <span class="total-label">TOTAL:</span>
            <span>₦{{ number_format($po->total_amount, 2) }}</span>
        </div>
        @if($po->amount_paid > 0)
        <div class="total-row">
            <span class="total-label">Paid:</span>
            <span>₦{{ number_format($po->amount_paid, 2) }}</span>
        </div>
        <div class="total-row">
            <span class="total-label">Balance:</span>
            <span>₦{{ number_format($po->total_amount - $po->amount_paid, 2) }}</span>
        </div>
        @endif
    </div>

    <div style="clear: both;"></div>

    @if($po->notes)
    <div class="info-section">
        <h3>Notes</h3>
        <p>{{ $po->notes }}</p>
    </div>
    @endif

    <div class="footer">
        <p>Generated on {{ now()->format('F d, Y H:i:s') }}</p>
        <p>Al-Khair Pharmacy Store - Inventory Management System</p>
    </div>
</body>
</html>
