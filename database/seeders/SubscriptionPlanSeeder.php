<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'key'            => 'platform',
                'name_az'        => 'Platforma girişi',
                'description_az' => 'Bina.az elanlarını görüntüləmə və filtrlər',
                'price'          => 19.00,
            ],
            [
                'key'            => 'requests',
                'name_az'        => 'İstəklər və Uyğunluqlar',
                'description_az' => 'Müştəri istəklərini qeyd etmə və uyğun elanların tapılması',
                'price'          => 5.00,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(['key' => $plan['key']], $plan);
        }
    }
}
