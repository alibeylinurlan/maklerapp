<?php

namespace App\Jobs;

use App\Models\CustomerRequest;
use App\Models\Property;
use App\Models\PropertyMatch;
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

        $requests = CustomerRequest::where('is_active', true)
            ->with('customer')
            ->get();

        $matchCount = 0;

        foreach ($requests as $request) {
            if ($this->matchesFilters($property, $request->filters)) {
                PropertyMatch::firstOrCreate(
                    [
                        'property_id' => $property->id,
                        'customer_request_id' => $request->id,
                    ],
                    [
                        'user_id' => $request->user_id,
                        'status' => 'new',
                    ]
                );
                $matchCount++;
            }
        }

        if ($matchCount > 0) {
            Log::info("Property {$property->bina_id} matched {$matchCount} requests");
        }
    }

    private function matchesFilters(Property $property, array $filters): bool
    {
        // Kateqoriya yoxlaması
        if (!empty($filters['categoryId']) && $property->category) {
            if ($property->category->bina_id !== $filters['categoryId']) {
                return false;
            }
        }

        // Lokasiya yoxlaması
        if (!empty($filters['locationIds']) && $property->location) {
            $requestLocationIds = array_map('strval', $filters['locationIds']);
            if (!in_array($property->location->bina_id, $requestLocationIds)) {
                return false;
            }
        }

        // Qiymət aralığı
        if (isset($filters['priceMin']) && $property->price < $filters['priceMin']) {
            return false;
        }
        if (isset($filters['priceMax']) && $property->price > $filters['priceMax']) {
            return false;
        }

        // Otaq sayı
        if (isset($filters['roomMin']) && $property->rooms < $filters['roomMin']) {
            return false;
        }
        if (isset($filters['roomMax']) && $property->rooms > $filters['roomMax']) {
            return false;
        }

        // Sahə
        if (isset($filters['areaMin']) && $property->area < $filters['areaMin']) {
            return false;
        }
        if (isset($filters['areaMax']) && $property->area > $filters['areaMax']) {
            return false;
        }

        // Təmir
        if (isset($filters['hasRepair']) && $filters['hasRepair'] && !$property->has_repair) {
            return false;
        }

        // Yalnız mülkiyyətçi
        if (!empty($filters['onlyOwner']) && $property->is_owner === false) {
            return false;
        }

        return true;
    }
}
