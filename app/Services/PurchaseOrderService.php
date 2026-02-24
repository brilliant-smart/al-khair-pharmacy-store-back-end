<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\ProductPriceHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseOrderService
{
    /**
     * Create a new purchase order
     */
    public function createPurchaseOrder(array $data, $user): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $user) {
            // Generate PO number
            $poNumber = PurchaseOrder::generatePoNumber();

            // Calculate totals
            $subtotal = 0;
            foreach ($data['items'] as $item) {
                $lineTotal = $item['quantity_ordered'] * $item['unit_cost'];
                $lineTotal -= $lineTotal * ($item['discount_percent'] ?? 0) / 100;
                $subtotal += $lineTotal;
            }

            // Nigeria uses inclusive pricing - no separate VAT calculation
            $totalAmount = $subtotal + ($data['shipping_cost'] ?? 0) - ($data['discount_amount'] ?? 0);

            // Create PO
            $po = PurchaseOrder::create([
                'po_number' => $poNumber,
                'supplier_id' => $data['supplier_id'],
                'department_id' => $data['department_id'] ?? $user->department_id,
                'created_by' => $user->id,
                'status' => $data['status'] ?? 'draft',
                'order_date' => $data['order_date'] ?? now(),
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'subtotal' => $subtotal,
                'vat_amount' => 0, // No separate VAT - inclusive pricing
                'discount_amount' => $data['discount_amount'] ?? 0,
                'shipping_cost' => $data['shipping_cost'] ?? 0,
                'total_amount' => $totalAmount,
                'notes' => $data['notes'] ?? null,
            ]);

            // Create PO items
            foreach ($data['items'] as $item) {
                $lineTotal = $item['quantity_ordered'] * $item['unit_cost'];
                $lineTotal -= $lineTotal * ($item['discount_percent'] ?? 0) / 100;

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $item['product_id'],
                    'quantity_ordered' => $item['quantity_ordered'],
                    'unit_cost' => $item['unit_cost'],
                    'vat_rate' => 0, // No separate VAT - inclusive pricing
                    'discount_percent' => $item['discount_percent'] ?? 0,
                    'line_total' => $lineTotal,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            Log::info('Purchase Order created', [
                'po_number' => $po->po_number,
                'supplier_id' => $po->supplier_id,
                'total_amount' => $po->total_amount,
                'user_id' => $user->id,
            ]);

            return $po->load(['items.product', 'supplier', 'department']);
        });
    }

    /**
     * Approve purchase order
     */
    public function approvePurchaseOrder(PurchaseOrder $po, $user): PurchaseOrder
    {
        if (!in_array($po->status, ['draft', 'pending'])) {
            throw new \Exception("Cannot approve PO with status: {$po->status}");
        }

        $po->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        Log::info('Purchase Order approved', [
            'po_number' => $po->po_number,
            'approved_by' => $user->id,
        ]);

        return $po;
    }

    /**
     * Receive goods (full or partial)
     */
    public function receiveGoods(PurchaseOrder $po, array $receivedItems, $user): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $receivedItems, $user) {
            if (!in_array($po->status, ['approved', 'ordered', 'partially_received'])) {
                throw new \Exception("Cannot receive goods for PO with status: {$po->status}");
            }

            foreach ($receivedItems as $itemData) {
                $poItem = PurchaseOrderItem::lockForUpdate()
                    ->where('purchase_order_id', $po->id)
                    ->where('product_id', $itemData['product_id'])
                    ->firstOrFail();

                $quantityToReceive = $itemData['quantity_received'];

                if ($quantityToReceive <= 0) {
                    continue;
                }

                if (($poItem->quantity_received + $quantityToReceive) > $poItem->quantity_ordered) {
                    throw new \Exception("Cannot receive more than ordered quantity for product ID: {$itemData['product_id']}");
                }

                // Update PO item
                $poItem->increment('quantity_received', $quantityToReceive);

                // Update product stock and cost
                $product = Product::lockForUpdate()->findOrFail($itemData['product_id']);
                
                $previousStock = $product->stock_quantity;
                $newStock = $previousStock + $quantityToReceive;

                // Update average cost price (weighted average)
                $totalValue = ($product->stock_quantity * $product->cost_price) + ($quantityToReceive * $poItem->unit_cost);
                $newCostPrice = $newStock > 0 ? $totalValue / $newStock : $poItem->unit_cost;

                // Record price history if price changed
                $oldPrice = $product->last_purchase_price;
                if ($oldPrice != $poItem->unit_cost) {
                    $priceChange = $poItem->unit_cost - $oldPrice;
                    $percentageChange = $oldPrice > 0 ? (($priceChange / $oldPrice) * 100) : 0;

                    ProductPriceHistory::create([
                        'product_id' => $product->id,
                        'old_price' => $oldPrice,
                        'new_price' => $poItem->unit_cost,
                        'price_change' => $priceChange,
                        'percentage_change' => $percentageChange,
                        'change_type' => 'purchase',
                        'supplier_name' => $po->supplier->name ?? null,
                        'reference_number' => $po->po_number,
                        'changed_by' => $user->id,
                        'notes' => $priceChange > 0 
                            ? "Price increased by ₦" . number_format(abs($priceChange), 2) . " (" . number_format(abs($percentageChange), 2) . "%)"
                            : "Price decreased by ₦" . number_format(abs($priceChange), 2) . " (" . number_format(abs($percentageChange), 2) . "%)",
                        'changed_at' => now(),
                    ]);
                }

                $product->update([
                    'stock_quantity' => $newStock,
                    'cost_price' => $newCostPrice,
                    'last_purchase_price' => $poItem->unit_cost,
                ]);

                // Create stock movement
                StockMovement::create([
                    'product_id' => $product->id,
                    'user_id' => $user->id,
                    'type' => 'purchase',
                    'quantity' => $quantityToReceive,
                    'previous_stock' => $previousStock,
                    'new_stock' => $newStock,
                    'unit_cost' => $poItem->unit_cost,
                    'notes' => "Received from PO: {$po->po_number}",
                ]);

                Log::info('Goods received', [
                    'po_number' => $po->po_number,
                    'product_id' => $product->id,
                    'quantity' => $quantityToReceive,
                    'new_cost_price' => $newCostPrice,
                ]);
            }

            // Update PO status
            $allItemsReceived = $po->items()
                ->whereColumn('quantity_received', '<', 'quantity_ordered')
                ->count() === 0;

            $po->update([
                'status' => $allItemsReceived ? 'received' : 'partially_received',
                'received_by' => $user->id,
                'received_at' => now(),
                'actual_delivery_date' => now()->toDateString(),
            ]);

            return $po->fresh(['items.product', 'supplier']);
        });
    }

    /**
     * Record payment
     */
    public function recordPayment(PurchaseOrder $po, float $amount, $user): PurchaseOrder
    {
        if ($amount <= 0) {
            throw new \Exception('Payment amount must be greater than zero');
        }

        $newAmountPaid = $po->amount_paid + $amount;

        if ($newAmountPaid > $po->total_amount) {
            throw new \Exception('Payment amount exceeds total due');
        }

        $paymentStatus = 'partially_paid';
        if ($newAmountPaid >= $po->total_amount) {
            $paymentStatus = 'paid';
        }

        $po->update([
            'amount_paid' => $newAmountPaid,
            'payment_status' => $paymentStatus,
        ]);

        Log::info('Payment recorded for PO', [
            'po_number' => $po->po_number,
            'amount' => $amount,
            'new_total_paid' => $newAmountPaid,
            'user_id' => $user->id,
        ]);

        return $po;
    }
}
