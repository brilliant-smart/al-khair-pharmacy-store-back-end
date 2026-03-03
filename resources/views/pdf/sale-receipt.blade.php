<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt #{{ $sale->receipt_number }}</title>
    <style>
        @page {
            size: 80mm auto;
            margin: 5mm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            line-height: 1.4;
            color: #000;
            width: 70mm;
        }
        
        .header {
            text-align: center;
            margin-bottom: 10px;
            border-bottom: 1px dashed #000;
            padding-bottom: 8px;
        }
        
        .store-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 3px;
        }
        
        .store-info {
            font-size: 9px;
            line-height: 1.3;
        }
        
        .receipt-title {
            font-size: 14px;
            font-weight: bold;
            margin: 8px 0;
            text-align: center;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            font-size: 10px;
        }
        
        .items-table {
            width: 100%;
            margin: 10px 0;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 5px 0;
        }
        
        .item-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .item-name {
            flex: 1;
            font-weight: bold;
        }
        
        .item-details {
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            color: #333;
            margin-bottom: 3px;
        }
        
        .totals {
            margin-top: 10px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .total-row.grand-total {
            font-size: 14px;
            font-weight: bold;
            border-top: 1px solid #000;
            border-bottom: 2px solid #000;
            padding: 5px 0;
            margin-top: 5px;
        }
        
        .payment-info {
            margin-top: 10px;
            border-top: 1px dashed #000;
            padding-top: 8px;
        }
        
        .footer {
            text-align: center;
            margin-top: 15px;
            border-top: 1px dashed #000;
            padding-top: 10px;
            font-size: 9px;
        }
        
        .thank-you {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        @media print {
            body {
                width: 70mm;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="store-name">AL-KHAIR PHARMACY & STORE</div>
        <div class="store-info">
            Bauchi, Nigeria<br>
            Tel: +234 XXX XXX XXXX<br>
            Email: info@alkhair.com
        </div>
    </div>

    <!-- Receipt Title -->
    <div class="receipt-title">SALES RECEIPT</div>

    <!-- Receipt Info -->
    <div class="info-row">
        <span>Receipt #:</span>
        <span><strong>{{ $sale->receipt_number }}</strong></span>
    </div>
    <div class="info-row">
        <span>Date:</span>
        <span>{{ \Carbon\Carbon::parse($sale->sale_date)->format('d/m/Y H:i') }}</span>
    </div>
    @if($sale->customer_name)
    <div class="info-row">
        <span>Customer:</span>
        <span>{{ $sale->customer_name }}</span>
    </div>
    @endif
    @if($sale->user)
    <div class="info-row">
        <span>Cashier:</span>
        <span>{{ $sale->user->name }}</span>
    </div>
    @endif

    <!-- Items Table -->
    <div class="items-table">
        @foreach($sale->items as $item)
        <div class="item-row">
            <div style="flex: 1;">
                <div class="item-name">{{ $item->product->name ?? 'Product #' . $item->product_id }}</div>
                <div class="item-details">
                    <span>{{ $item->quantity }} x ₦{{ number_format($item->unit_price, 2) }}</span>
                    <span>₦{{ number_format($item->quantity * $item->unit_price, 2) }}</span>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <!-- Totals -->
    <div class="totals">
        <div class="total-row">
            <span>Subtotal:</span>
            <span>₦{{ number_format($sale->subtotal, 2) }}</span>
        </div>
        <div class="total-row grand-total">
            <span>TOTAL:</span>
            <span>₦{{ number_format($sale->total_amount, 2) }}</span>
        </div>
    </div>

    <!-- Payment Info -->
    <div class="payment-info">
        <div class="info-row">
            <span>Payment Method:</span>
            <span style="text-transform: uppercase;">{{ str_replace('_', ' ', $sale->payment_method) }}</span>
        </div>
        @if($sale->payment_method == 'cash')
        <div class="info-row">
            <span>Amount Paid:</span>
            <span>₦{{ number_format($sale->amount_paid ?? $sale->total_amount, 2) }}</span>
        </div>
        @if(($sale->amount_paid ?? $sale->total_amount) > $sale->total_amount)
        <div class="info-row">
            <span>Change:</span>
            <span>₦{{ number_format(($sale->amount_paid ?? $sale->total_amount) - $sale->total_amount, 2) }}</span>
        </div>
        @endif
        @endif
    </div>

    <!-- Footer -->
    <div class="footer">
        <div class="thank-you">THANK YOU FOR YOUR PATRONAGE!</div>
        <div>Visit us again soon</div>
        <div style="margin-top: 5px;">{{ now()->format('d/m/Y H:i:s') }}</div>
    </div>
</body>
</html>
