<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\PurchaseOrder;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FinancialReportService
{
    /**
     * Get comprehensive financial overview
     */
    public function getFinancialOverview($startDate = null, $endDate = null, $departmentId = null): array
    {
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();

        // Sales data
        $salesQuery = Sale::whereBetween('sale_date', [$start, $end]);
        if ($departmentId) {
            $salesQuery->where('department_id', $departmentId);
        }

        $totalRevenue = $salesQuery->sum('total_amount');
        $totalCOGS = $salesQuery->sum('cost_of_goods_sold');
        $totalGrossProfit = $salesQuery->sum('gross_profit');
        $totalSales = $salesQuery->count();

        // Purchase data
        $purchaseQuery = PurchaseOrder::whereBetween('order_date', [$start, $end])
            ->whereIn('status', ['received', 'partially_received']);
        if ($departmentId) {
            $purchaseQuery->where('department_id', $departmentId);
        }

        $totalPurchases = $purchaseQuery->sum('total_amount');
        $totalPurchasesPaid = $purchaseQuery->sum('amount_paid');
        $totalPurchasesOutstanding = $totalPurchases - $totalPurchasesPaid;

        // Receivables (credit sales)
        $receivablesQuery = Sale::where('payment_status', '!=', 'paid');
        if ($departmentId) {
            $receivablesQuery->where('department_id', $departmentId);
        }
        $totalReceivables = $receivablesQuery->sum('amount_due');

        // Current stock value
        $stockQuery = Product::where('is_active', true);
        if ($departmentId) {
            $stockQuery->where('department_id', $departmentId);
        }
        $currentStockValue = $stockQuery->get()->sum(function ($product) {
            return $product->stock_quantity * $product->cost_price;
        });

        // Profit margin
        $profitMargin = $totalRevenue > 0 ? ($totalGrossProfit / $totalRevenue) * 100 : 0;

        return [
            'period' => [
                'start_date' => $start->format('Y-m-d'),
                'end_date' => $end->format('Y-m-d'),
            ],
            'revenue' => [
                'total_sales_count' => $totalSales,
                'total_revenue' => round($totalRevenue, 2),
                'average_sale_value' => $totalSales > 0 ? round($totalRevenue / $totalSales, 2) : 0,
            ],
            'costs' => [
                'cost_of_goods_sold' => round($totalCOGS, 2),
                'total_purchases' => round($totalPurchases, 2),
            ],
            'profit' => [
                'gross_profit' => round($totalGrossProfit, 2),
                'profit_margin_percent' => round($profitMargin, 2),
            ],
            'cash_flow' => [
                'cash_in' => round($salesQuery->where('payment_status', 'paid')->sum('amount_paid'), 2),
                'cash_out' => round($totalPurchasesPaid, 2),
                'net_cash_flow' => round($salesQuery->where('payment_status', 'paid')->sum('amount_paid') - $totalPurchasesPaid, 2),
            ],
            'outstanding' => [
                'receivables' => round($totalReceivables, 2),
                'payables' => round($totalPurchasesOutstanding, 2),
            ],
            'inventory' => [
                'current_stock_value' => round($currentStockValue, 2),
                'total_products' => $stockQuery->count(),
                'low_stock_items' => $stockQuery->whereColumn('stock_quantity', '<=', 'low_stock_threshold')->count(),
                'out_of_stock_items' => $stockQuery->where('stock_quantity', 0)->count(),
            ],
        ];
    }

    /**
     * Get profit/loss by department
     */
    public function getProfitLossByDepartment($startDate = null, $endDate = null): array
    {
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();

        $departments = DB::table('departments')
            ->leftJoin('sales', function ($join) use ($start, $end) {
                $join->on('departments.id', '=', 'sales.department_id')
                     ->whereBetween('sales.sale_date', [$start, $end])
                     ->whereNull('sales.deleted_at');
            })
            ->select(
                'departments.id',
                'departments.name',
                DB::raw('COUNT(sales.id) as total_sales'),
                DB::raw('COALESCE(SUM(sales.total_amount), 0) as total_revenue'),
                DB::raw('COALESCE(SUM(sales.cost_of_goods_sold), 0) as total_cogs'),
                DB::raw('COALESCE(SUM(sales.gross_profit), 0) as total_profit')
            )
            ->groupBy('departments.id', 'departments.name')
            ->get()
            ->map(function ($dept) {
                $profitMargin = $dept->total_revenue > 0 
                    ? ($dept->total_profit / $dept->total_revenue) * 100 
                    : 0;

                return [
                    'department_id' => $dept->id,
                    'department_name' => $dept->name,
                    'total_sales' => $dept->total_sales,
                    'total_revenue' => round($dept->total_revenue, 2),
                    'total_cogs' => round($dept->total_cogs, 2),
                    'total_profit' => round($dept->total_profit, 2),
                    'profit_margin_percent' => round($profitMargin, 2),
                ];
            });

        return $departments->toArray();
    }

    /**
     * Get cashier performance report
     */
    public function getCashierPerformance($startDate = null, $endDate = null, $departmentId = null): array
    {
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();

        $query = DB::table('users')
            ->leftJoin('sales', function ($join) use ($start, $end) {
                $join->on('users.id', '=', 'sales.cashier_id')
                     ->whereBetween('sales.sale_date', [$start, $end])
                     ->whereNull('sales.deleted_at');
            });

        if ($departmentId) {
            $query->where('users.department_id', $departmentId);
        }

        $cashiers = $query->select(
                'users.id',
                'users.name',
                'users.department_id',
                DB::raw('COUNT(sales.id) as total_sales'),
                DB::raw('COALESCE(SUM(sales.total_amount), 0) as total_revenue'),
                DB::raw('COALESCE(SUM(sales.gross_profit), 0) as total_profit')
            )
            ->groupBy('users.id', 'users.name', 'users.department_id')
            ->having('total_sales', '>', 0)
            ->orderByDesc('total_revenue')
            ->get()
            ->map(function ($cashier) {
                return [
                    'cashier_id' => $cashier->id,
                    'cashier_name' => $cashier->name,
                    'total_sales' => $cashier->total_sales,
                    'total_revenue' => round($cashier->total_revenue, 2),
                    'total_profit' => round($cashier->total_profit, 2),
                    'average_sale_value' => $cashier->total_sales > 0 
                        ? round($cashier->total_revenue / $cashier->total_sales, 2) 
                        : 0,
                ];
            });

        return $cashiers->toArray();
    }

    /**
     * Get top selling products
     */
    public function getTopSellingProducts($startDate = null, $endDate = null, $limit = 20, $departmentId = null): array
    {
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();

        $query = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->whereBetween('sales.sale_date', [$start, $end])
            ->whereNull('sales.deleted_at');

        if ($departmentId) {
            $query->where('products.department_id', $departmentId);
        }

        $products = $query->select(
                'products.id',
                'products.name',
                'products.sku',
                DB::raw('SUM(sale_items.quantity) as total_quantity_sold'),
                DB::raw('SUM(sale_items.line_total) as total_revenue'),
                DB::raw('SUM(sale_items.line_profit) as total_profit')
            )
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('total_quantity_sold')
            ->limit($limit)
            ->get()
            ->map(function ($product) {
                return [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'quantity_sold' => $product->total_quantity_sold,
                    'total_revenue' => round($product->total_revenue, 2),
                    'total_profit' => round($product->total_profit, 2),
                ];
            });

        return $products->toArray();
    }

    /**
     * Detect stock variances (potential theft/loss)
     */
    public function detectStockVariances($departmentId = null): array
    {
        $query = Product::with('department')
            ->where('is_active', true);

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        $products = $query->get();

        $variances = [];

        foreach ($products as $product) {
            // Calculate expected stock based on movements
            $initialStock = 0; // You could store this or calculate from first movement
            $purchases = $product->stockMovements()->where('type', 'purchase')->sum('quantity');
            $sales = $product->stockMovements()->where('type', 'sale')->sum('quantity');
            $adjustments = $product->stockMovements()->where('type', 'adjustment')->sum('quantity');
            
            $expectedStock = $initialStock + $purchases + abs($sales) + $adjustments;
            $actualStock = $product->stock_quantity;
            $variance = $actualStock - $expectedStock;

            // Flag significant variances (more than 5% or absolute value > 10)
            $variancePercent = $expectedStock > 0 ? abs($variance / $expectedStock) * 100 : 0;
            
            if (abs($variance) > 10 || $variancePercent > 5) {
                $variances[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'department' => $product->department->name,
                    'expected_stock' => $expectedStock,
                    'actual_stock' => $actualStock,
                    'variance' => $variance,
                    'variance_percent' => round($variancePercent, 2),
                    'variance_value' => round($variance * $product->cost_price, 2),
                    'severity' => abs($variance) > 50 || $variancePercent > 20 ? 'high' : 'medium',
                ];
            }
        }

        return $variances;
    }

    /**
     * Get products requiring reorder
     */
    public function getReorderReport($departmentId = null): array
    {
        $query = Product::with('department')
            ->where('is_active', true)
            ->whereColumn('stock_quantity', '<=', 'reorder_point');

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        $products = $query->get()->map(function ($product) {
            $suggestedOrderQty = max($product->max_stock_level - $product->stock_quantity, $product->reorder_point);

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'department' => $product->department->name,
                'current_stock' => $product->stock_quantity,
                'reorder_point' => $product->reorder_point,
                'max_stock_level' => $product->max_stock_level,
                'suggested_order_qty' => $suggestedOrderQty,
                'last_purchase_price' => $product->last_purchase_price,
                'estimated_cost' => round($suggestedOrderQty * $product->last_purchase_price, 2),
            ];
        });

        return $products->toArray();
    }

    /**
     * Get expiring products (for pharmacy)
     */
    public function getExpiringProducts($departmentId = null, $daysAhead = 30): array
    {
        // Ensure daysAhead is an integer
        $daysAhead = (int) $daysAhead;
        $futureDate = Carbon::now()->addDays($daysAhead);

        $query = Product::with('department')
            ->where('is_active', true)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', $futureDate)
            ->where('expiry_date', '>=', Carbon::now());

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        $products = $query->orderBy('expiry_date')->get()->map(function ($product) {
            $expiryDate = Carbon::parse($product->expiry_date);
            $daysUntilExpiry = Carbon::now()->diffInDays($expiryDate, false);
            $stockValue = $product->stock_quantity * $product->cost_price;

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'batch_number' => $product->batch_number,
                'department' => $product->department->name,
                'expiry_date' => $expiryDate->format('Y-m-d'),
                'days_until_expiry' => $daysUntilExpiry,
                'stock_quantity' => $product->stock_quantity,
                'stock_value' => round($stockValue, 2),
                'urgency' => $daysUntilExpiry <= 7 ? 'critical' : ($daysUntilExpiry <= 14 ? 'high' : 'medium'),
            ];
        });

        return $products->toArray();
    }
}
