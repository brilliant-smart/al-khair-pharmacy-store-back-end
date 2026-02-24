<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ControlledSubstanceLog;
use App\Models\Product;
use Illuminate\Http\Request;

class ControlledSubstanceController extends Controller
{
    /**
     * Get controlled substance logs
     */
    public function logs(Request $request)
    {
        $query = ControlledSubstanceLog::with(['product', 'prescription', 'dispensedBy', 'verifiedBy']);

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
        }

        if ($request->filled('start_date')) {
            $query->where('transaction_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->where('transaction_date', '<=', $request->end_date);
        }

        $logs = $query->latest('transaction_date')->paginate($request->input('per_page', 15));

        return response()->json($logs);
    }

    /**
     * Get controlled substance inventory
     */
    public function inventory(Request $request)
    {
        $query = Product::where('is_controlled_substance', true)
            ->where('is_active', true)
            ->with('department');

        if ($request->filled('schedule')) {
            $query->where('controlled_substance_schedule', $request->schedule);
        }

        $products = $query->get()->map(function ($product) {
            $lastLog = ControlledSubstanceLog::where('product_id', $product->id)
                ->latest('transaction_date')
                ->first();

            return [
                'product' => $product,
                'current_balance' => $lastLog?->balance_after ?? $product->stock_quantity,
                'last_transaction_date' => $lastLog?->transaction_date,
            ];
        });

        return response()->json($products);
    }

    /**
     * Get controlled substance report
     */
    public function report(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = ControlledSubstanceLog::with(['product', 'dispensedBy']);

        if ($startDate) {
            $query->where('transaction_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('transaction_date', '<=', $endDate);
        }

        $logs = $query->get();

        $summary = [
            'total_transactions' => $logs->count(),
            'received' => $logs->where('transaction_type', 'received')->sum('quantity'),
            'dispensed' => $logs->where('transaction_type', 'dispensed')->sum('quantity'),
            'destroyed' => $logs->where('transaction_type', 'destroyed')->sum('quantity'),
            'returned' => $logs->where('transaction_type', 'returned')->sum('quantity'),
            'transferred' => $logs->where('transaction_type', 'transfer')->sum('quantity'),
        ];

        return response()->json([
            'summary' => $summary,
            'logs' => $logs,
        ]);
    }
}
