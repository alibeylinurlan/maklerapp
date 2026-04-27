<?php

namespace App\Jobs;

use App\Models\CustomerRequest;
use App\Models\Property;
use App\Models\PropertyMatch;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MatchNewPropertyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        private int $propertyId,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $property = Property::find($this->propertyId);
        if (!$property) return;

        // business elanları skip
        if ($property->is_business) return;

        $matchCount = 0;
        $telegram = new TelegramService();

        CustomerRequest::where('is_active', true)
            ->with(['customer', 'user'])
            ->chunk(200, function ($requests) use ($property, $telegram, &$matchCount) {
                foreach ($requests as $request) {
                    if (!self::matchesFilters($property, $request->filters)) continue;

                    $user = $request->user;
                    $canMatch = $user && (
                        $user->hasAnyRole(['superadmin', 'admin', 'developer'])
                        || $user->hasFeature('matches')
                    );
                    if (!$canMatch) continue;

                    $match = PropertyMatch::firstOrCreate(
                        [
                            'property_id'         => $property->id,
                            'customer_request_id' => $request->id,
                        ],
                        [
                            'user_id'     => $request->user_id,
                            'customer_id' => $request->customer_id,
                            'status'      => 'new',
                        ]
                    );

                    if ($match->wasRecentlyCreated && $request->customer_id) {
                        \App\Models\Customer::where('id', $request->customer_id)
                            ->update(['last_activity_at' => now()]);
                    }

                    $shouldNotify = $match->wasRecentlyCreated
                        || ($match->updated_at < $property->updated_at);

                    if ($shouldNotify) {
                        $match->touch();
                    }

                    $canTelegram = $user->hasAnyRole(['superadmin', 'admin', 'developer'])
                        || $user->hasFeature('telegram_notify');

                    if ($shouldNotify && $canTelegram && $request->notify_telegram && $user->telegram_user_id) {
                        $price    = number_format($property->price) . ' ' . $property->currency;
                        $rooms    = $property->rooms ? "{$property->rooms} otaq" : '';
                        $area     = $property->area ? "{$property->area} m²" : '';
                        $location = $property->location_full_name ?? '';
                        $url      = $property->full_url ?? '';

                        $text = "🔔 <b>Yeni uyğunluq!</b> • {$request->name}\n"
                              . "👤 <b>Müştəri:</b> {$request->customer?->name}\n\n"
                              . "💰 {$price}" . ($rooms ? " • {$rooms}" : '') . ($area ? " • {$area}" : '') . "\n"
                              . ($location ? "📍 {$location}\n" : '')
                              . ($url ? "\n<a href=\"{$url}\">Elana bax →</a>" : '');

                        $telegram->send($user->telegram_user_id, $text);
                    }

                    $matchCount++;
                }
            });

        if ($matchCount > 0) {
            Log::info("Property {$property->bina_id} matched {$matchCount} requests");
        }
    }

    public static function matchesFilters(Property $property, array $filters): bool
    {
        // Kateqoriya (DB id)
        if (!empty($filters['categoryId'])) {
            if ((string) $property->category_id !== (string) $filters['categoryId']) {
                return false;
            }
        }

        // Lokasiya (DB id) — seçilmiş location və onun child/grandchild-ları uyğun gəlir
        if (!empty($filters['locationIds'])) {
            $ids = array_map('strval', $filters['locationIds']);
            $level1 = \App\Models\Location::whereIn('parent_id', $ids)->pluck('id')->map('strval')->toArray();
            $level2 = $level1 ? \App\Models\Location::whereIn('parent_id', $level1)->pluck('id')->map('strval')->toArray() : [];
            $allIds = array_merge($ids, $level1, $level2);
            if (!in_array((string) $property->location_id, $allIds)) {
                return false;
            }
        }

        // Qiymət
        if (isset($filters['priceMin']) && $property->price < $filters['priceMin']) return false;
        if (isset($filters['priceMax']) && $property->price > $filters['priceMax']) return false;

        // Otaq
        if (isset($filters['roomMin']) && $property->rooms < $filters['roomMin']) return false;
        if (isset($filters['roomMax']) && $property->rooms > $filters['roomMax']) return false;

        // Mərtəbə
        if (isset($filters['floorMin']) && $property->floor < $filters['floorMin']) return false;
        if (isset($filters['floorMax']) && $property->floor > $filters['floorMax']) return false;

        // Sahə
        if (isset($filters['areaMin']) && $property->area < $filters['areaMin']) return false;
        if (isset($filters['areaMax']) && $property->area > $filters['areaMax']) return false;

        // Boolean-lar
        if (!empty($filters['hasMortgage'])   && !$property->has_mortgage)    return false;
        if (!empty($filters['hasBillOfSale']) && !$property->has_bill_of_sale) return false;
        if (!empty($filters['notFirstFloor']) && (int) $property->floor <= 1)  return false;
        if (!empty($filters['notTopFloor'])   && $property->floor_total && (int) $property->floor >= $property->floor_total) return false;
        if (!empty($filters['onlyTopFloor'])  && $property->floor_total && (int) $property->floor < $property->floor_total)  return false;

        return true;
    }
}
