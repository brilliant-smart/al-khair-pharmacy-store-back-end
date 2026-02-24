<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockTransfer;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockTransferController extends Controller
{
    /**
     * Get all stock transfers
     */
    public function index(Request $request)
    {
        $query = StockTransfer::with([
            'product',
            'fromDepartment',
            'toDepartment',
            'requestedBy',
            'approvedBy',
            'receivedBy'
        ]);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by department
        if ($request->filled('from_department_id')) {
            $query->where('from_department_id', $request->from_department_id);
        }

        if ($request->filled('to_department_id')) {
            $query->where('to_department_id', $request->to_department_id);
        }

        $transfers = $query->latest()->paginate($request->input('per_page', 15));

        return response()->json($transfers);
    }

    /**
     * Create a stock transfer request
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'from_department_id' => 'required|exists:departments,id',
            'to_department_id' => 'required|exists:departments,id|different:from_department_id',
            'quantity' => 'required|integer|min:1',
            'batch_number' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        // Check if source department has enough stock
        $product = Product::findOrFail($validated['product_id']);
        
        // For simplicity, we check overall stock. In a real system, you'd check department-specific stock
        if ($product->stock_quantity < $validated['quantity']) {
            return response()->json([
                'message' => 'Insufficient stock in source department',
            ], 422);
        }

        $validated['transfer_number'] = StockTransfer::generateTransferNumber();
        $validated['requested_by'] = auth()->id();
        $validated['status'] = 'pending';

        $transfer = StockTransfer::create($validated);

        return response()->json([
            'message' => 'Stock transfer request created successfully',
            'transfer' => $transfer->load(['product', 'fromDepartment', 'toDepartment']),
        ], 201);
    }

    /**
     * Get a specific transfer
     */
    public function show(StockTransfer $transfer)
    {
        return response()->json($transfer->load([
            'product',
            'fromDepartment',
            'toDepartment',
            'requestedBy',
            'approvedBy',
            'receivedBy'
        ]));
    }

    /**
     * Approve a transfer
     */
    public function approve(StockTransfer $transfer)
    {
        if ($transfer->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending transfers can be approved',
            ], 422);
        }

        $transfer->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Transfer approved successfully',
            'transfer' => $transfer,
        ]);
    }

    /**
     * Mark transfer as in transit
     */
    public function markInTransit(StockTransfer $transfer)
    {
        if ($transfer->status !== 'approved') {
            return response()->json([
                'message' => 'Only approved transfers can be marked as in transit',
            ], 422);
        }

        DB::transaction(function () use ($transfer) {
            // Deduct stock from source department
            StockMovement::create([
                'product_id' => $transfer->product_id,
                'type' => 'adjustment',
                'quantity' => -$transfer->quantity,
                'unit_cost' => $transfer->product->cost_price,
                'reference_type' => 'StockTransfer',
                'reference_id' => $transfer->id,
                'performed_by' => auth()->id(),
                'notes' => "Transfer to {$transfer->toDepartment->name} (Transfer: {$transfer->transfer_number})",
            ]);

            $transfer->product->decrement('stock_quantity', $transfer->quantity);

            $transfer->update([
                'status' => 'in_transit',
            ]);
        });

        return response()->json([
            'message' => 'Transfer marked as in transit successfully',
            'transfer' => $transfer,
        ]);
    }

    /**
     * Receive a transfer
     */
    public function receive(StockTransfer $transfer)
    {
        if (!in_array($transfer->status, ['approved', 'in_transit'])) {
            return response()->json([
                'message' => 'Only approved or in-transit transfers can be received',
            ], 422);
        }

        DB::transaction(function () use ($transfer) {
            // Add stock to destination department
            StockMovement::create([
                'product_id' => $transfer->product_id,
                'type' => 'adjustment',
                'quantity' => $transfer->quantity,
                'unit_cost' => $transfer->product->cost_price,
                'reference_type' => 'StockTransfer',
                'reference_id' => $transfer->id,
                'performed_by' => auth()->id(),
                'notes' => "Received from {$transfer->fromDepartment->name} (Transfer: {$transfer->transfer_number})",
            ]);

            $transfer->product->increment('stock_quantity', $transfer->quantity);

            $transfer->update([
                'status' => 'received',
                'received_by' => auth()->id(),
                'received_at' => now(),
            ]);
        });

        return response()->json([
            'message' => 'Transfer received successfully',
            'transfer' => $transfer,
        ]);
    }

    /**
     * Reject a transfer
     */
    public function reject(Request $request, StockTransfer $transfer)
    {
        if ($transfer->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending transfers can be rejected',
            ], 422);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string',
        ]);

        $transfer->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Transfer rejected successfully',
            'transfer' => $transfer,
        ]);
    }

    /**
     * Cancel a transfer
     */
    public function cancel(StockTransfer $transfer)
    {
        if (!in_array($transfer->status, ['pending', 'approved'])) {
            return response()->json([
                'message' => 'Only pending or approved transfers can be cancelled',
            ], 422);
        }

        $transfer->update([
            'status' => 'cancelled',
        ]);

        return response()->json([
            'message' => 'Transfer cancelled successfully',
            'transfer' => $transfer,
        ]);
    }
}
