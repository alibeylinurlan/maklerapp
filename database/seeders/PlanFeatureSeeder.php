<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanFeatureSeeder extends Seeder
{
    public function run(): void
    {
        // Mövcud köhnə planları sil
        DB::table('subscription_plans')->whereIn('key', ['platform', 'requests'])->delete();

        // Yeni planlar
        $plans = [
            ['key' => 'normal',  'name_az' => 'Normal',        'price' => 60.00,  'is_active' => true],
            ['key' => 'gold',    'name_az' => 'Gold',           'price' => 100.00, 'is_active' => true],
            ['key' => 'premium', 'name_az' => 'Premium',        'price' => 130.00, 'is_active' => true],
            ['key' => 'ultra',   'name_az' => 'Ultra Premium',  'price' => 160.00, 'is_active' => true],
        ];

        foreach ($plans as $plan) {
            DB::table('subscription_plans')->updateOrInsert(['key' => $plan['key']], array_merge($plan, [
                'created_at' => now(), 'updated_at' => now(),
            ]));
        }

        // Feature-lər
        $features = [
            ['key' => 'properties_view', 'name_az' => 'Elan baxışı və axtarışı',        'sort_order' => 1],
            ['key' => 'live_feed',       'name_az' => 'Canlı elanlar',                   'sort_order' => 2],
            ['key' => 'customers',       'name_az' => 'Müştəri yaratma',                 'sort_order' => 3],
            ['key' => 'saved_lists',     'name_az' => 'Elanları save etmə + kolleksiya', 'sort_order' => 4],
            ['key' => 'requests',        'name_az' => 'İstək yaratma',                   'sort_order' => 5],
            ['key' => 'matches',         'name_az' => 'Uyğunluqlar',                     'sort_order' => 6],
            ['key' => 'notes',           'name_az' => 'Elana not yazma',                 'sort_order' => 7],
            ['key' => 'telegram_notify', 'name_az' => 'Telegram bildirişi',              'sort_order' => 8],
        ];

        foreach ($features as $f) {
            DB::table('features')->updateOrInsert(['key' => $f['key']], array_merge($f, [
                'created_at' => now(), 'updated_at' => now(),
            ]));
        }

        // Plan → feature əlaqələri
        $planFeatures = [
            'normal'  => ['properties_view'],
            'gold'    => ['properties_view', 'live_feed', 'customers', 'saved_lists', 'requests'],
            'premium' => ['properties_view', 'live_feed', 'customers', 'saved_lists', 'requests', 'matches', 'notes', 'telegram_notify'],
            'ultra'   => ['properties_view', 'live_feed', 'customers', 'saved_lists', 'requests', 'matches', 'notes', 'telegram_notify'],
        ];

        DB::table('plan_features')->delete();

        foreach ($planFeatures as $planKey => $featureKeys) {
            $planId = DB::table('subscription_plans')->where('key', $planKey)->value('id');
            foreach ($featureKeys as $featureKey) {
                DB::table('plan_features')->insert([
                    'plan_id'     => $planId,
                    'feature_key' => $featureKey,
                ]);
            }
        }
    }
}
