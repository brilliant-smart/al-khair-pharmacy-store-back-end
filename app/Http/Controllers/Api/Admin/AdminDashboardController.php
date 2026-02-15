<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Product;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function stats(Request $request)
    {
        return response()->json([
            'totalUsers'    => User::count(),
            'activeUsers'   => User::where('is_active', true)->count(),
            'totalProducts' => Product::count(),
        ]);
    }
}
