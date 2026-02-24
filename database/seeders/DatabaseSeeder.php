<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Seed departments if they don't exist
        if (Department::count() === 0) {
            $this->call(DepartmentSeeder::class);
        }

        // Get departments for user assignment
        $pharmacy = Department::where('slug', 'pharmacy')->first();
        $superstore = Department::where('slug', 'superstore')->first();

        // Create Master Admin
        User::firstOrCreate(
            ['email' => 'admin@alkhair.com'],
            [
                'name' => 'Master Admin',
                'password' => Hash::make('password'),
                'role' => 'master_admin',
                'department_id' => null,
                'is_active' => true,
            ]
        );

        // Create Section Head for Pharmacy
        User::firstOrCreate(
            ['email' => 'pharmacy@alkhair.com'],
            [
                'name' => 'Pharmacy Manager',
                'password' => Hash::make('password'),
                'role' => 'section_head',
                'department_id' => $pharmacy->id,
                'is_active' => true,
            ]
        );

        // Create Section Head for Superstore
        User::firstOrCreate(
            ['email' => 'superstore@alkhair.com'],
            [
                'name' => 'Superstore Manager',
                'password' => Hash::make('password'),
                'role' => 'section_head',
                'department_id' => $superstore->id,
                'is_active' => true,
            ]
        );

        // Create sample suppliers
        $supplier1 = Supplier::firstOrCreate(
            ['code' => 'SUP-001'],
            [
            'name' => 'MedSupply Nigeria Ltd',
            'contact_person' => 'John Okafor',
            'email' => 'contact@medsupply.ng',
            'phone' => '+234 803 123 4567',
            'address' => '12 Medical Road, Ikeja, Lagos',
            'city' => 'Lagos',
            'state' => 'Lagos',
            'payment_terms' => 'credit_30',
            'is_active' => true,
            ]
        );

        $supplier2 = Supplier::firstOrCreate(
            ['code' => 'SUP-002'],
            [
            'name' => 'Global Foods Distribution',
            'contact_person' => 'Mary Adeyemi',
            'email' => 'sales@globalfoods.ng',
            'phone' => '+234 805 987 6543',
            'address' => '45 Commerce Avenue, VI, Lagos',
            'city' => 'Lagos',
            'state' => 'Lagos',
            'payment_terms' => 'credit_14',
            'is_active' => true,
            ]
        );

        // Create sample products with cost tracking
        Product::create([
            'name' => 'Paracetamol 500mg (Pack of 100)',
            'slug' => 'paracetamol-500mg',
            'sku' => 'PHM-PAR-500',
            'description' => 'Pain relief and fever reducer',
            'department_id' => $pharmacy->id,
            'price' => 2500.00,
            'cost_price' => 1800.00,
            'last_purchase_price' => 1800.00,
            'stock_quantity' => 150,
            'low_stock_threshold' => 20,
            'reorder_point' => 30,
            'max_stock_level' => 300,
            'is_active' => true,
            'is_featured' => true,
        ]);

        Product::create([
            'name' => 'Golden Penny Semovita 2kg',
            'slug' => 'golden-penny-semovita-2kg',
            'sku' => 'SS-GP-SEM-2',
            'description' => 'Premium quality semovita',
            'department_id' => $superstore->id,
            'price' => 3200.00,
            'cost_price' => 2400.00,
            'last_purchase_price' => 2400.00,
            'stock_quantity' => 80,
            'low_stock_threshold' => 15,
            'reorder_point' => 20,
            'max_stock_level' => 200,
            'is_active' => true,
            'is_featured' => false,
        ]);

        $this->command->info('Database seeded successfully!');
        $this->command->info('Login credentials:');
        $this->command->info('Master Admin: admin@alkhair.com / password');
        $this->command->info('Pharmacy Manager: pharmacy@alkhair.com / password');
        $this->command->info('Superstore Manager: superstore@alkhair.com / password');
    }
}
