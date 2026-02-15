<?php

use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\Admin\UserController;
use Illuminate\Support\Facades\Password;
use Illuminate\Http\Request;

//Login
Route::post('/login', [AuthController::class, 'login']);
//Logout
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

/* Public */
/* Products */
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product:slug}', [ProductController::class, 'show']);
/* Departments */
Route::get('/departments', [DepartmentController::class, 'index']);
Route::get('/departments/{department}', [DepartmentController::class, 'show']);

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
Route::middleware('auth:sanctum')->group(function () {
    /* Authenticated user */
    Route::get('/me', function (Request $request) {
        return response()->json([
            'id'            => $request->user()->id,
            'name'          => $request->user()->name,
            'email'         => $request->user()->email,
            'role'          => $request->user()->role,
            'department_id' => $request->user()->department_id,
        ]);
    });
    /* Products (Admin uses ID via separate routes) */
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/admin/products/{product}', [ProductController::class, 'update']);
    Route::post('/admin/products/{product}', [ProductController::class, 'update']); // Accept POST for FormData with _method
    Route::delete('/admin/products/{product}', [ProductController::class, 'destroy']);

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
});
