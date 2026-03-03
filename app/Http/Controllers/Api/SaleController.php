<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    protected $saleService;

    public function __construct(SaleService $saleService)
    {
        $this->saleService = $saleService;
    }

    /**
     * Display a listing of sales
     * GET /api/sales
     */
    public function index(Request $request)
    {
        $query = Sale::with(['department', 'cashier']);

        // Department filter (for section heads)
        if ($request->user()->role === 'section_head') {
            $query->where('department_id', $request->user()->department_id);
        } elseif ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        // Sale type filter
        if ($request->filled('sale_type')) {
            $query->where('sale_type', $request->sale_type);
        }

        // Payment status filter
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        // Cashier filter
        if ($request->filled('cashier_id')) {
            $query->where('cashier_id', $request->cashier_id);
        }

        // Date range
        if ($request->filled('start_date')) {
            $query->whereDate('sale_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('sale_date', '<=', $request->end_date);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('sale_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        $sales = $query->latest('sale_date')
            ->paginate($request->input('per_page', 15));

        return response()->json($sales);
    }

    /**
     * Store a new sale
     * POST /api/sales
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'department_id' => 'nullable|exists:departments,id',
            'sale_type' => 'nullable|in:cash,credit,online,pos',
            'payment_status' => 'nullable|in:unpaid,partially_paid,paid',
            'sale_date' => 'nullable|date',
            'discount_amount' => 'nullable|numeric|min:0',
            'amount_paid' => 'nullable|numeric|min:0',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.notes' => 'nullable|string',
        ]);

        try {
            $sale = $this->saleService->createSale($validated, $request->user());

            return response()->json([
                'message' => 'Sale created successfully',
                'sale' => $sale,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create sale',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Display the specified sale
     * GET /api/sales/{sale}
     */
    public function show(Sale $sale)
    {
        // Authorization check
        $user = request()->user();
        if ($user->role === 'section_head' && $sale->department_id !== $user->department_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $sale->load(['items.product', 'department', 'cashier']);

        return response()->json($sale);
    }

    /**
     * Record payment for credit sale
     * POST /api/sales/{sale}/payment
     */
    public function recordPayment(Request $request, Sale $sale)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        try {
            $updatedSale = $this->saleService->recordPayment(
                $sale,
                $validated['amount'],
                $request->user()
            );

            return response()->json([
                'message' => 'Payment recorded successfully',
                'sale' => $updatedSale,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to record payment',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get sales summary/statistics
     * GET /api/sales/summary
     */
    public function summary(Request $request)
    {
        $query = Sale::query();

        // Department filter
        if ($request->user()->role === 'section_head') {
            $query->where('department_id', $request->user()->department_id);
        } elseif ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        // Date range (default to today)
        $startDate = $request->input('start_date', now()->startOfDay());
        $endDate = $request->input('end_date', now()->endOfDay());

        $query->whereBetween('sale_date', [$startDate, $endDate]);

        $summary = [
            'total_sales' => $query->count(),
            'total_revenue' => $query->sum('total_amount'),
            'total_cost' => $query->sum('cost_of_goods_sold'),
            'total_profit' => $query->sum('gross_profit'),
            'average_sale_value' => $query->avg('total_amount'),
            'total_outstanding' => Sale::where('payment_status', '!=', 'paid')
                ->sum('amount_due'),
            'sales_by_type' => Sale::query()
                ->selectRaw('sale_type, COUNT(*) as count, SUM(total_amount) as total')
                ->whereBetween('sale_date', [$startDate, $endDate])
                ->groupBy('sale_type')
                ->get(),
            'sales_by_payment_status' => Sale::query()
                ->selectRaw('payment_status, COUNT(*) as count, SUM(total_amount) as total')
                ->whereBetween('sale_date', [$startDate, $endDate])
                ->groupBy('payment_status')
                ->get(),
        ];

        return response()->json($summary);
    }

    /**
     * Export sales as CSV
     * GET /api/sales/export
     */
    public function exportCsv(Request $request)
    {
        $query = Sale::with(['department', 'cashier', 'items.product']);

        // Apply same filters as index
        if ($request->user()->role === 'section_head') {
            $query->where('department_id', $request->user()->department_id);
        } elseif ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('sale_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('sale_date', '<=', $request->end_date);
        }

        $sales = $query->latest('sale_date')->get();

        $filename = 'sales-export-' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($sales) {
            $file = fopen('php://output', 'w');

            // Header
            fputcsv($file, [
                'Sale Number',
                'Date',
                'Department',
                'Cashier',
                'Type',
                'Customer',
                'Total Amount',
                'COGS',
                'Gross Profit',
                'Profit Margin %',
                'Payment Status',
                'Amount Paid',
                'Amount Due'
            ]);

            // Data
            foreach ($sales as $sale) {
                fputcsv($file, [
                    $sale->sale_number,
                    $sale->sale_date->format('Y-m-d H:i'),
                    $sale->department->name,
                    $sale->cashier->name,
                    $sale->sale_type,
                    $sale->customer_name,
                    number_format($sale->total_amount, 2),
                    number_format($sale->cost_of_goods_sold, 2),
                    number_format($sale->gross_profit, 2),
                    number_format($sale->profit_margin, 2),
                    $sale->payment_status,
                    number_format($sale->amount_paid, 2),
                    number_format($sale->amount_due, 2),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Print receipt
     * GET /api/sales/{sale}/receipt
     */
    public function printReceipt(Request $request, Sale $sale)
    {
        // Support token authentication via query parameter for print preview
        $token = $request->input('token') ?? $request->bearerToken();
        
        if ($token) {
            $tokenModel = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            if ($tokenModel) {
                $user = $tokenModel->tokenable;
                
                // Authorization check
                if ($user->role === 'section_head' && $sale->department_id !== $user->department_id) {
                    return response()->json(['message' => 'Unauthorized'], 403);
                }
                
                $sale->load(['items.product', 'user']);
                return view('pdf.sale-receipt', compact('sale'));
            }
        }
        
        // If no valid token, check if user is authenticated via sanctum middleware
        $user = request()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        
        if ($user->role === 'section_head' && $sale->department_id !== $user->department_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $sale->load(['items.product', 'user']);
        return view('pdf.sale-receipt', compact('sale'));
    }

    /**
     * Delete sale (soft delete) - Only for today's sales
     * DELETE /api/sales/{sale}
     */
    public function destroy(Sale $sale)
    {
        // Only master admin can delete
        if (request()->user()->role !== 'master_admin') {
            return response()->json(['message' => 'Only master admin can delete sales'], 403);
        }

        // Only allow deletion of today's sales
        if (!$sale->sale_date->isToday()) {
            return response()->json([
                'message' => 'Can only delete sales from today',
            ], 422);
        }

        $sale->delete();

        return response()->json([
            'message' => 'Sale deleted successfully',
        ]);
    }
}
