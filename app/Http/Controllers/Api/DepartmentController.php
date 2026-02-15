<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Department;

class DepartmentController extends Controller
{
    /**
     * Public: List all departments
     */
    public function index()
    {
        return response()->json(
            Department::select('id', 'name', 'slug')
                ->orderBy('name')
                ->get()
        );
    }

    /**
     * Public: Single department with active products (optional use)
     */
    public function show(Department $department)
    {
        return response()->json(
            $department->load([
                'products' => fn ($q) => $q->where('is_active', true),
            ])
        );
    }
}
