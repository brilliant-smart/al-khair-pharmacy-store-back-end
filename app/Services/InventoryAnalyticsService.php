<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InventoryAnalyticsService
{
    /**
     * Get comprehensive inventory dashboard statistics
     */
    public function getDashboardStats(?string $startDate = null, ?string $endDate = null, ?User $user = null): array
    {
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->subDays(30);
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now();

        return [
            'overview' => $this->getOverviewMetrics($user),
            'stock_status' => $this->getStockStatusBreakdown($user),
            'movement_summary' => $this->getMovementSummary($start, $end, $user),
            'top_products' => $this->getTopProducts($start, $end, 10, $user),
            'inventory_value' => $this->getInventoryValue($user),
            'alerts' => $this->getAlerts($user),
        ];
    }

    /**
     * Get overview metrics
     */
    private function getOverviewMetrics(?User $user = null): array
    {
        $query = Product::where('is_active', true);
        
        // Section heads only see their department
        if ($user && $user->role === 'section_head' && $user->department_id) {
            $query->where('department_id', $user->department_id);
        }
        
        $totalProducts = $query->count();
        $totalStockValue = (clone $query)
            ->select(DB::raw('SUM(stock_quantity * price) as total_value'))
            ->value('total_value') ?? 0;
        
        $totalStockUnits = (clone $query)->sum('stock_quantity');

        return [
            'total_products' => $totalProducts,
            'total_stock_units' => $totalStockUnits,
            'total_stock_value' => round($totalStockValue, 2),
            'average_stock_per_product' => $totalProducts > 0 ? round($totalStockUnits / $totalProducts, 2) : 0,
        ];
    }

    /**
     * Get stock status breakdown
     */
    private function getStockStatusBreakdown(?User $user = null): array
    {
        $query = Product::where('is_active', true);
        
        // Section heads only see their department
        if ($user && $user->role === 'section_head' && $user->department_id) {
            $query->where('department_id', $user->department_id);
        }
        
        $products = $query->get();
        
        $inStock = $products->filter(fn($p) => $p->stock_status === 'in_stock')->count();
        $lowStock = $products->filter(fn($p) => $p->stock_status === 'low_stock')->count();
        $outOfStock = $products->filter(fn($p) => $p->stock_status === 'out_of_stock')->count();

        return [
            'in_stock' => $inStock,
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
            'total' => $products->count(),
        ];
    }

    /**
     * Get movement summary for date range
     */
    private function getMovementSummary(Carbon $start, Carbon $end, ?User $user = null): array
    {
        $query = StockMovement::whereBetween('created_at', [$start, $end]);
        
        // Section heads only see movements for their department's products
        if ($user && $user->role === 'section_head' && $user->department_id) {
            $query->whereHas('product', function ($q) use ($user) {
                $q->where('department_id', $user->department_id);
            });
        }
        
        $movements = $query->get();

        $summary = [
            'total_movements' => $movements->count(),
            'by_type' => [],
            'net_change' => 0,
        ];

        $types = ['purchase', 'sale', 'adjustment', 'damage', 'return'];
        foreach ($types as $type) {
            $typeMovements = $movements->where('type', $type);
            $summary['by_type'][$type] = [
                'count' => $typeMovements->count(),
                'total_quantity' => $typeMovements->sum('quantity'),
            ];
        }

        $summary['net_change'] = $movements->sum('quantity');

        return $summary;
    }

    /**
     * Get top products by movement activity
     */
    private function getTopProducts(Carbon $start, Carbon $end, int $limit = 10, ?User $user = null): array
    {
        $soldQuery = StockMovement::with('product')
            ->whereBetween('created_at', [$start, $end])
            ->where('type', 'sale');
        
        // Section heads only see their department
        if ($user && $user->role === 'section_head' && $user->department_id) {
            $soldQuery->whereHas('product', function ($q) use ($user) {
                $q->where('department_id', $user->department_id);
            });
        }
        
        $topSold = $soldQuery
            ->select('product_id', DB::raw('ABS(SUM(quantity)) as total_sold'))
            ->groupBy('product_id')
            ->orderByDesc('total_sold')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name ?? 'Unknown',
                    'quantity_sold' => $item->total_sold,
                    'current_stock' => $item->product->stock_quantity ?? 0,
                ];
            });

        $purchasedQuery = StockMovement::with('product')
            ->whereBetween('created_at', [$start, $end])
            ->where('type', 'purchase');
        
        // Section heads only see their department
        if ($user && $user->role === 'section_head' && $user->department_id) {
            $purchasedQuery->whereHas('product', function ($q) use ($user) {
                $q->where('department_id', $user->department_id);
            });
        }
        
        $topPurchased = $purchasedQuery
            ->select('product_id', DB::raw('SUM(quantity) as total_purchased'))
            ->groupBy('product_id')
            ->orderByDesc('total_purchased')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name ?? 'Unknown',
                    'quantity_purchased' => $item->total_purchased,
                    'current_stock' => $item->product->stock_quantity ?? 0,
                ];
            });

        return [
            'top_sold' => $topSold,
            'top_purchased' => $topPurchased,
        ];
    }

    /**
     * Get total inventory value
     */
    private function getInventoryValue(?User $user = null): array
    {
        $query = Product::with('department')->where('is_active', true);
        
        // Section heads only see their department
        if ($user && $user->role === 'section_head' && $user->department_id) {
            $query->where('department_id', $user->department_id);
        }
        
        $byDepartment = $query->get()
            ->groupBy('department_id')
            ->map(function ($products, $deptId) {
                $dept = $products->first()->department;
                return [
                    'department_id' => $deptId,
                    'department_name' => $dept->name ?? 'Unknown',
                    'total_units' => $products->sum('stock_quantity'),
                    'total_value' => round($products->sum(fn($p) => $p->stock_quantity * $p->price), 2),
                    'product_count' => $products->count(),
                ];
            })
            ->values();

        return [
            'by_department' => $byDepartment,
            'grand_total' => round($byDepartment->sum('total_value'), 2),
        ];
    }

    /**
     * Get alerts and warnings
     */
    private function getAlerts(?User $user = null): array
    {
        $lowStockQuery = Product::where('is_active', true)
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->where('stock_quantity', '>', 0)
            ->with('department');
        
        // Section heads only see their department
        if ($user && $user->role === 'section_head' && $user->department_id) {
            $lowStockQuery->where('department_id', $user->department_id);
        }
        
        $lowStock = $lowStockQuery->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'current_stock' => $p->stock_quantity,
                'threshold' => $p->low_stock_threshold,
                'department' => $p->department->name ?? 'N/A',
            ]);

        $outOfStockQuery = Product::where('is_active', true)
            ->where('stock_quantity', 0)
            ->with('department');
        
        // Section heads only see their department
        if ($user && $user->role === 'section_head' && $user->department_id) {
            $outOfStockQuery->where('department_id', $user->department_id);
        }
        
        $outOfStock = $outOfStockQuery->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'department' => $p->department->name ?? 'N/A',
            ]);

        return [
            'low_stock_items' => $lowStock,
            'out_of_stock_items' => $outOfStock,
        ];
    }

    /**
     * Get detailed movement report
     */
    public function getMovementReport(array $filters = [], ?User $user = null): array
    {
        $query = StockMovement::with(['product', 'user']);

        // Section heads only see movements for their department's products
        if ($user && $user->role === 'section_head' && $user->department_id) {
            $query->whereHas('product', function ($q) use ($user) {
                $q->where('department_id', $user->department_id);
            });
        }

        // Apply filters
        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['start_date'])->startOfDay());
        }

        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['end_date'])->endOfDay());
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        $movements = $query->orderByDesc('created_at')
            ->limit($filters['limit'] ?? 100)
            ->get()
            ->map(function ($movement) {
                return [
                    'id' => $movement->id,
                    'product_id' => $movement->product_id,
                    'product_name' => $movement->product->name ?? 'Deleted Product',
                    'sku' => $movement->product->sku ?? 'N/A',
                    'type' => $movement->type,
                    'quantity' => $movement->quantity,
                    'previous_quantity' => $movement->previous_quantity,
                    'new_quantity' => $movement->new_quantity,
                    'notes' => $movement->notes,
                    'user_name' => $movement->user->name ?? 'Unknown User',
                    'created_at' => $movement->created_at->toISOString(),
                ];
            });

        return [
            'movements' => $movements,
            'total_count' => $movements->count(),
        ];
    }

    /**
     * Get inventory turnover rate
     */
    public function getTurnoverRate(int $days = 30, ?User $user = null): array
    {
        $startDate = Carbon::now()->subDays($days);
        
        $query = Product::where('is_active', true);
        
        // Section heads only see their department
        if ($user && $user->role === 'section_head' && $user->department_id) {
            $query->where('department_id', $user->department_id);
        }
        
        $products = $query->with(['stockMovements' => function ($query) use ($startDate) {
                $query->where('created_at', '>=', $startDate)
                    ->where('type', 'sale');
            }])
            ->get()
            ->map(function ($product) use ($days) {
                $totalSold = abs($product->stockMovements->sum('quantity'));
                $avgStock = $product->stock_quantity; // Simplified - could calculate average over period
                
                $turnoverRate = $avgStock > 0 ? round(($totalSold / $avgStock) * (365 / $days), 2) : 0;
                
                return [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'units_sold' => $totalSold,
                    'current_stock' => $product->stock_quantity,
                    'turnover_rate' => $turnoverRate,
                    'days_of_stock' => $turnoverRate > 0 ? round(365 / $turnoverRate, 1) : 999,
                ];
            })
            ->sortByDesc('turnover_rate')
            ->values();

        return [
            'period_days' => $days,
            'products' => $products,
            'average_turnover' => round($products->avg('turnover_rate'), 2),
        ];
    }
}
