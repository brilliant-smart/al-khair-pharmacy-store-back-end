<?php

use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\InventoryAnalyticsController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\BatchTrackingController;
use App\Http\Controllers\Api\StockTransferController;
use App\Http\Controllers\Api\AutoReorderController;
use App\Http\Controllers\Api\AdvancedReportController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PrescriptionController;
use App\Http\Controllers\Api\ControlledSubstanceController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\CustomerAuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\ProductReviewController;
use App\Http\Controllers\Api\EcommerceSettingController;
use App\Http\Controllers\Api\CRMController;
use App\Http\Controllers\Api\SecurityController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\PriceHistoryController;
use App\Http\Controllers\Api\SupplierPriceComparisonController;
use Illuminate\Support\Facades\Password;
use Illuminate\Http\Request;

//Login (Rate limited to prevent brute force)
Route::middleware('throttle:5,1')->post('/login', [AuthController::class, 'login']);
//Logout
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

/* Public */
/* Products */
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product:slug}', [ProductController::class, 'show']);
    Route::get('/products/barcode/search', [ProductController::class, 'searchByBarcode']);
    /* Departments */
    Route::get('/departments', [DepartmentController::class, 'index']);
    Route::get('/departments/{department}', [DepartmentController::class, 'show']);
    
    /* E-commerce Public Routes */
    Route::post('/customer/register', [CustomerAuthController::class, 'register']);
    Route::post('/customer/login', [CustomerAuthController::class, 'login']);
    Route::get('/ecommerce/settings', [EcommerceSettingController::class, 'show']);
    Route::get('/products/{productId}/reviews', [ProductReviewController::class, 'index']);
});

//Forget Password
Route::post('/forgot-password', function (Request $request) {
    $request->validate(['email' => 'required|email']);

    $status = Password::sendResetLink(
        $request->only('email')
    );

    return response()->json([
        'message' => __($status),
    ]);
});

//Reset password
Route::post('/reset-password', function (Request $request) {
    $request->validate([
        'token'    => 'required',
        'email'    => 'required|email',
        'password' => 'required|min:8|confirmed',
    ]);

    $status = Password::reset(
        $request->only('email', 'password', 'password_confirmation', 'token'),
        function ($user, $password) {
            $user->forceFill([
                'password' => bcrypt($password),
            ])->save();
        }
    );

    return response()->json([
        'message' => __($status),
    ]);
});

/* Protected */
Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
    /* Authenticated user */
    Route::get('/me', function (Request $request) {
        return response()->json([
            'id'            => $request->user()->id,
            'name'          => $request->user()->name,
            'email'         => $request->user()->email,
            'role'          => $request->user()->role,
            'department_id' => $request->user()->department_id,
            'avatar_url'    => $request->user()->avatar_url,
        ]);
    });
    
    /* User Profile */
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);
    
    /* Products (Admin uses ID via separate routes) - Hide cost data from non-admin */
    Route::middleware('hide.profit')->group(function () {
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/admin/products/{product}', [ProductController::class, 'update']);
        Route::post('/admin/products/{product}', [ProductController::class, 'update']); // Accept POST for FormData with _method
        Route::delete('/admin/products/{product}', [ProductController::class, 'destroy']);
    });

    /* Inventory Management */
    Route::prefix('inventory')->group(function () {
        // Stock mutation endpoints - more restrictive rate limit
        Route::middleware('throttle:30,1')->group(function () {
            Route::post('/products/{product}/add-stock', [InventoryController::class, 'addStock']);
            Route::post('/products/{product}/reduce-stock', [InventoryController::class, 'reduceStock']);
            Route::post('/products/{product}/adjust-stock', [InventoryController::class, 'adjustStock']);
            Route::post('/bulk-update', [InventoryController::class, 'bulkUpdateStock']);
        });
        
        Route::get('/products/{product}/stock-history', [InventoryController::class, 'getStockHistory']);
        Route::get('/low-stock', [InventoryController::class, 'getLowStockProducts']);
        Route::get('/out-of-stock', [InventoryController::class, 'getOutOfStockProducts']);
        Route::get('/summary', [InventoryController::class, 'getInventorySummary']);
        
        /* Inventory Analytics & Reports */
        Route::prefix('analytics')->group(function () {
            Route::get('/dashboard', [InventoryAnalyticsController::class, 'dashboard']);
            Route::get('/movements', [InventoryAnalyticsController::class, 'movements']);
            Route::get('/turnover', [InventoryAnalyticsController::class, 'turnover']);
            Route::get('/export', [InventoryAnalyticsController::class, 'export']);
        });
    });

    /* Admin dashboard */
    Route::get(
        '/admin/dashboard-stats',
        [AdminDashboardController::class, 'stats']
    );

    /* Admin users */
    Route::get('/admin/users', [UserController::class, 'index']);
    Route::post('/admin/users', [UserController::class, 'store']);
    Route::patch('/admin/users/{user}', [UserController::class, 'update']);
    Route::delete('/admin/users/{user}', [UserController::class, 'destroy']);

    /* Suppliers */
    Route::apiResource('suppliers', SupplierController::class);
    Route::post('/suppliers/{supplier}/toggle-status', [SupplierController::class, 'toggleStatus']);

    /* Price History */
    Route::get('/price-history', [PriceHistoryController::class, 'index']);
    Route::get('/products/{productId}/price-history', [PriceHistoryController::class, 'productHistory']);

    /* Supplier Price Comparison */
    Route::get('/reports/supplier-price-comparison', [SupplierPriceComparisonController::class, 'index']);
    Route::get('/reports/supplier-performance', [SupplierPriceComparisonController::class, 'supplierPerformance']);
    Route::get('/reports/best-supplier/{productId}', [SupplierPriceComparisonController::class, 'bestSupplier']);

    /* Purchase Orders */
    Route::post('/purchase-orders/price-comparison', [PurchaseOrderController::class, 'getPriceComparison']);
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index']);
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store']);
    Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show']);
    Route::put('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update']);
    Route::delete('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'destroy']);
    Route::post('/purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve']);
    Route::post('/purchase-orders/{purchaseOrder}/reject', [PurchaseOrderController::class, 'reject']);
    Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel']);
    Route::post('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receiveGoods']);
    Route::post('/purchase-orders/{purchaseOrder}/record-payment', [PurchaseOrderController::class, 'recordPayment']);
    Route::get('/purchase-orders/{purchaseOrder}/pdf', [PurchaseOrderController::class, 'exportPdf']);
    Route::get('/purchase-orders-export', [PurchaseOrderController::class, 'exportAll']);
    Route::get('/purchase-orders/{purchaseOrder}/csv', [PurchaseOrderController::class, 'exportCsv']);

    /* Sales - Hide profit data from non-admin users */
    Route::middleware('hide.profit')->group(function () {
        Route::get('/sales', [SaleController::class, 'index']);
        Route::post('/sales', [SaleController::class, 'store']);
        Route::get('/sales/summary', [SaleController::class, 'summary']);
        Route::get('/sales/export', [SaleController::class, 'exportCsv']);
        Route::get('/sales/{sale}', [SaleController::class, 'show']);
        Route::get('/sales/{sale}/receipt', [SaleController::class, 'printReceipt']);
        Route::post('/sales/{sale}/payment', [SaleController::class, 'recordPayment']);
        Route::delete('/sales/{sale}', [SaleController::class, 'destroy']);
    });

    /* Financial Reports */
    Route::prefix('reports')->group(function () {
        Route::get('/financial-overview', [ReportController::class, 'financialOverview']);
        Route::get('/profit-loss-by-department', [ReportController::class, 'profitLossByDepartment']);
        Route::get('/cashier-performance', [ReportController::class, 'cashierPerformance']);
        Route::get('/top-selling-products', [ReportController::class, 'topSellingProducts']);
        Route::get('/stock-variances', [ReportController::class, 'stockVariances']);
        Route::get('/reorder', [ReportController::class, 'reorderReport']);
        Route::get('/expiring-products', [ReportController::class, 'expiringProducts']);
    });

    /* Batch Tracking */
    Route::prefix('batches')->group(function () {
        Route::get('/', [BatchTrackingController::class, 'index']);
        Route::post('/', [BatchTrackingController::class, 'store']);
        Route::get('/expiring', [BatchTrackingController::class, 'expiring']);
        Route::get('/expired', [BatchTrackingController::class, 'expired']);
        Route::get('/inventory-report', [BatchTrackingController::class, 'inventoryReport']);
        Route::get('/{batch}', [BatchTrackingController::class, 'show']);
        Route::put('/{batch}', [BatchTrackingController::class, 'update']);
        Route::post('/{batch}/mark-expired', [BatchTrackingController::class, 'markExpired']);
    });

    /* Stock Transfers */
    Route::prefix('stock-transfers')->group(function () {
        Route::get('/', [StockTransferController::class, 'index']);
        Route::post('/', [StockTransferController::class, 'store']);
        Route::get('/{transfer}', [StockTransferController::class, 'show']);
        Route::post('/{transfer}/approve', [StockTransferController::class, 'approve']);
        Route::post('/{transfer}/mark-in-transit', [StockTransferController::class, 'markInTransit']);
        Route::post('/{transfer}/receive', [StockTransferController::class, 'receive']);
        Route::post('/{transfer}/reject', [StockTransferController::class, 'reject']);
        Route::post('/{transfer}/cancel', [StockTransferController::class, 'cancel']);
    });

    /* Auto Reorder */
    Route::prefix('auto-reorder')->group(function () {
        Route::get('/suggestions', [AutoReorderController::class, 'suggestions']);
        Route::post('/trigger-check', [AutoReorderController::class, 'triggerCheck']);
        Route::get('/logs', [AutoReorderController::class, 'logs']);
        Route::get('/statistics', [AutoReorderController::class, 'statistics']);
    });

    /* Advanced Reports */
    Route::prefix('advanced-reports')->group(function () {
        Route::get('/inventory-aging', [AdvancedReportController::class, 'inventoryAging']);
        Route::get('/supplier-performance', [AdvancedReportController::class, 'supplierPerformance']);
        Route::get('/abc-analysis', [AdvancedReportController::class, 'abcAnalysis']);
        Route::get('/dead-stock', [AdvancedReportController::class, 'deadStock']);
        Route::get('/stockout', [AdvancedReportController::class, 'stockout']);
        Route::get('/sales-forecast', [AdvancedReportController::class, 'salesForecast']);
    });

    /* Notifications */
    Route::prefix('notifications')->group(function () {
        Route::get('/settings', [NotificationController::class, 'getSettings']);
        Route::put('/settings/{key}', [NotificationController::class, 'updateSetting']);
        Route::get('/logs', [NotificationController::class, 'getLogs']);
        Route::post('/test', [NotificationController::class, 'testNotification']);
    });

    /* Prescriptions */
    Route::prefix('prescriptions')->group(function () {
        Route::get('/', [PrescriptionController::class, 'index']);
        Route::post('/', [PrescriptionController::class, 'store']);
        Route::get('/{prescription}', [PrescriptionController::class, 'show']);
        Route::post('/{prescription}/dispense', [PrescriptionController::class, 'dispense']);
        Route::post('/{prescription}/cancel', [PrescriptionController::class, 'cancel']);
    });

    /* Controlled Substances */
    Route::prefix('controlled-substances')->group(function () {
        Route::get('/logs', [ControlledSubstanceController::class, 'logs']);
        Route::get('/inventory', [ControlledSubstanceController::class, 'inventory']);
        Route::get('/report', [ControlledSubstanceController::class, 'report']);
    });

    /* Audit Logs */
    Route::prefix('audit-logs')->group(function () {
        Route::get('/', [AuditLogController::class, 'index']);
        Route::get('/statistics', [AuditLogController::class, 'statistics']);
        Route::get('/{auditLog}', [AuditLogController::class, 'show']);
    });

    // Backup & Restore
    Route::prefix('backups')->group(function () {
        // All authenticated users can list, create, download, and delete backups
        Route::get('/', [BackupController::class, 'index']);
        Route::post('/create', [BackupController::class, 'create']);
        Route::get('/{filename}/download', [BackupController::class, 'download']);
        Route::delete('/{filename}', [BackupController::class, 'destroy']);
        
        // Upload and Restore - Master Admin only (checked in controller)
        Route::post('/upload', [BackupController::class, 'upload']);
        Route::post('/{filename}/restore', [BackupController::class, 'restore']);
    });

    // Alerts (Low Stock, Expiry)
    Route::prefix('alerts')->group(function () {
        Route::get('/summary', [AlertController::class, 'summary']);
        Route::get('/low-stock', [AlertController::class, 'lowStock']);
        Route::get('/expiring-batches', [AlertController::class, 'expiringBatches']);
        Route::get('/expired-batches', [AlertController::class, 'expiredBatches']);
    });

    /* E-commerce Settings (Admin only) */
    Route::put('/ecommerce/settings', [EcommerceSettingController::class, 'update']);

    /* CRM (Admin only) */
    Route::prefix('crm')->group(function () {
        Route::get('/settings', [CRMController::class, 'getSettings']);
        Route::put('/settings', [CRMController::class, 'updateSettings']);
        Route::get('/coupons', [CRMController::class, 'getCoupons']);
        Route::post('/coupons', [CRMController::class, 'createCoupon']);
        Route::get('/segments', [CRMController::class, 'getSegments']);
        Route::post('/segments', [CRMController::class, 'createSegment']);
    });

    /* Security (Admin only) */
    Route::prefix('security')->group(function () {
        Route::get('/settings', [SecurityController::class, 'getSettings']);
        Route::put('/settings', [SecurityController::class, 'updateSettings']);
        Route::get('/ip-whitelist', [SecurityController::class, 'getWhitelist']);
        Route::post('/ip-whitelist', [SecurityController::class, 'addToWhitelist']);
        Route::delete('/ip-whitelist/{id}', [SecurityController::class, 'removeFromWhitelist']);
        Route::get('/login-attempts', [SecurityController::class, 'getLoginAttempts']);
        Route::post('/2fa/enable', [SecurityController::class, 'enable2FA']);
        Route::post('/2fa/disable', [SecurityController::class, 'disable2FA']);
    });

    /* Webhooks & Integrations (Admin only) */
    Route::prefix('webhooks')->group(function () {
        Route::get('/', [WebhookController::class, 'getWebhooks']);
        Route::post('/', [WebhookController::class, 'createWebhook']);
        Route::delete('/{id}', [WebhookController::class, 'deleteWebhook']);
    });
});

/* Customer Routes (requires customer authentication) */
Route::middleware(['auth:sanctum', 'throttle:120,1'])->prefix('customer')->group(function () {
    Route::post('/logout', [CustomerAuthController::class, 'logout']);
    Route::get('/profile', [CustomerAuthController::class, 'profile']);
    Route::put('/profile', [CustomerAuthController::class, 'updateProfile']);
    Route::put('/profile/password', [CustomerAuthController::class, 'changePassword']);
    
    /* Cart */
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);
    Route::put('/cart/{cartItem}', [CartController::class, 'update']);
    Route::delete('/cart/{cartItem}', [CartController::class, 'destroy']);
    Route::delete('/cart', [CartController::class, 'clear']);
    
    /* Orders */
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
    
    /* Wishlist */
    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist', [WishlistController::class, 'store']);
    Route::delete('/wishlist/{wishlist}', [WishlistController::class, 'destroy']);
    
    /* Reviews */
    Route::post('/reviews', [ProductReviewController::class, 'store']);
    
    /* Loyalty */
    Route::get('/loyalty/points', [CRMController::class, 'getLoyaltyPoints']);
    Route::get('/loyalty/transactions', [CRMController::class, 'getLoyaltyTransactions']);
});
