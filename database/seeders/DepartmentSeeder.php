<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Department;
class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            ['name' => 'Pharmacy', 'slug' => 'pharmacy'],
            ['name' => 'Superstore', 'slug' => 'superstore'],
            ['name' => 'Electronics & Kitchen', 'slug' => 'electronics-kitchen'],
            ['name' => 'Textiles & Materials', 'slug' => 'textiles'],
            ['name' => 'Baby Care', 'slug' => 'baby-care'],
            ['name' => 'Wholesale', 'slug' => 'wholesale'],
        ];

        Department::insert($departments);
    }
}
