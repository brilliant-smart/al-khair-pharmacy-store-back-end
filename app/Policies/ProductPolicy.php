<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use App\Models\Department;

class ProductPolicy
{
    /**
     * View product (for inventory and stock history)
     */
    public function view(User $user, Product $product): bool
    {
        // Master admin can view any product
        if ($user->role === 'master_admin') {
            return true;
        }

        // Section head can only view products in their department
        return $user->role === 'section_head'
            && $user->department_id === $product->department_id;
    }

    /**
     * Create product in a department
     */
    public function create(User $user, Department $department): bool
    {
        // Master admin can create in any department
        if ($user->role === 'master_admin') {
            return true;
        }

        // Section head can only create in their own department
        return $user->role === 'section_head'
            && $user->department_id === $department->id;
    }

    /**
     * Update product
     */
    public function update(User $user, Product $product): bool
    {
        if ($user->role === 'master_admin') {
            return true;
        }

        return $user->role === 'section_head'
            && $user->department_id === $product->department_id;
    }

    /**
     * Delete product
     */
    public function delete(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }

}
