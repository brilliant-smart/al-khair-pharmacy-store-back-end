<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    public function awardPoints(Customer $customer, Order $order)
    {
        $settings = DB::table('crm_settings')->first();
        if (!$settings || !$settings->loyalty_program_enabled) {
            return;
        }

        $points = floor($order->total_amount * $settings->points_per_currency);
        
        $loyalty = DB::table('customer_loyalty_points')->updateOrInsert(
            ['customer_id' => $customer->id],
            [
                'points' => DB::raw("points + {$points}"),
                'lifetime_points' => DB::raw("lifetime_points + {$points}"),
                'updated_at' => now(),
            ]
        );

        DB::table('loyalty_transactions')->insert([
            'customer_id' => $customer->id,
            'type' => 'earned',
            'points' => $points,
            'balance_before' => DB::table('customer_loyalty_points')->where('customer_id', $customer->id)->value('points') - $points,
            'balance_after' => DB::table('customer_loyalty_points')->where('customer_id', $customer->id)->value('points'),
            'order_id' => $order->id,
            'description' => "Points earned from order {$order->order_number}",
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->updateTier($customer);
    }

    private function updateTier(Customer $customer)
    {
        $loyalty = DB::table('customer_loyalty_points')->where('customer_id', $customer->id)->first();
        $lifetimePoints = $loyalty->lifetime_points ?? 0;

        $tier = 'bronze';
        if ($lifetimePoints >= 10000) $tier = 'platinum';
        elseif ($lifetimePoints >= 5000) $tier = 'gold';
        elseif ($lifetimePoints >= 1000) $tier = 'silver';

        DB::table('customer_loyalty_points')->where('customer_id', $customer->id)->update(['tier' => $tier]);
    }
}
