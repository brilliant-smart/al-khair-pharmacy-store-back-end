<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FinancialReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    protected $financialReportService;

    public function __construct(FinancialReportService $financialReportService)
    {
        $this->financialReportService = $financialReportService;
    }

    /**
     * Get comprehensive financial overview
     * GET /api/reports/financial-overview
     */
    public function financialOverview(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        // Section heads can only see their department
        $departmentId = null;
        if ($request->user()->role === 'section_head') {
            $departmentId = $request->user()->department_id;
        } elseif ($request->filled('department_id')) {
            $departmentId = $request->department_id;
        }

        $overview = $this->financialReportService->getFinancialOverview(
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null,
            $departmentId
        );

        return response()->json($overview);
    }

    /**
     * Get profit/loss by department
     * GET /api/reports/profit-loss-by-department
     */
    public function profitLossByDepartment(Request $request)
    {
        // Only master admin can see all departments
        if ($request->user()->role !== 'master_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $report = $this->financialReportService->getProfitLossByDepartment(
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null
        );

        return response()->json($report);
    }

    /**
     * Get cashier performance report
     * GET /api/reports/cashier-performance
     */
    public function cashierPerformance(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $departmentId = null;
        if ($request->user()->role === 'section_head') {
            $departmentId = $request->user()->department_id;
        } elseif ($request->filled('department_id')) {
            $departmentId = $request->department_id;
        }

        $report = $this->financialReportService->getCashierPerformance(
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null,
            $departmentId
        );

        return response()->json($report);
    }

    /**
     * Get top selling products
     * GET /api/reports/top-selling-products
     */
    public function topSellingProducts(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'limit' => 'nullable|integer|min:1|max:100',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $departmentId = null;
        if ($request->user()->role === 'section_head') {
            $departmentId = $request->user()->department_id;
        } elseif ($request->filled('department_id')) {
            $departmentId = $request->department_id;
        }

        $products = $this->financialReportService->getTopSellingProducts(
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null,
            $validated['limit'] ?? 20,
            $departmentId
        );

        return response()->json($products);
    }

    /**
     * Detect stock variances (potential theft/loss)
     * GET /api/reports/stock-variances
     */
    public function stockVariances(Request $request)
    {
        $validated = $request->validate([
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $departmentId = null;
        if ($request->user()->role === 'section_head') {
            $departmentId = $request->user()->department_id;
        } elseif ($request->filled('department_id')) {
            $departmentId = $request->department_id;
        }

        $variances = $this->financialReportService->detectStockVariances($departmentId);

        return response()->json([
            'variances' => $variances,
            'total_variances' => count($variances),
            'high_severity_count' => collect($variances)->where('severity', 'high')->count(),
            'total_variance_value' => round(collect($variances)->sum('variance_value'), 2),
        ]);
    }

    /**
     * Get reorder report
     * GET /api/reports/reorder
     */
    public function reorderReport(Request $request)
    {
        $validated = $request->validate([
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $departmentId = null;
        if ($request->user()->role === 'section_head') {
            $departmentId = $request->user()->department_id;
        } elseif ($request->filled('department_id')) {
            $departmentId = $request->department_id;
        }

        $products = $this->financialReportService->getReorderReport($departmentId);

        return response()->json([
            'products' => $products,
            'total_products_to_reorder' => count($products),
            'total_estimated_cost' => round(collect($products)->sum('estimated_cost'), 2),
        ]);
    }

    /**
     * Get expiring products (for pharmacy)
     * GET /api/reports/expiring-products
     */
    public function expiringProducts(Request $request)
    {
        $validated = $request->validate([
            'department_id' => 'nullable|exists:departments,id',
            'days_ahead' => 'nullable|integer|min:1|max:365',
        ]);

        $departmentId = null;
        if ($request->user()->role === 'section_head') {
            $departmentId = $request->user()->department_id;
        } elseif ($request->filled('department_id')) {
            $departmentId = $request->department_id;
        }

        $products = $this->financialReportService->getExpiringProducts(
            $departmentId,
            $validated['days_ahead'] ?? 30
        );

        return response()->json([
            'products' => $products,
            'total_expiring_products' => count($products),
            'critical_count' => collect($products)->where('urgency', 'critical')->count(),
            'total_stock_value_at_risk' => round(collect($products)->sum('stock_value'), 2),
        ]);
    }
}
