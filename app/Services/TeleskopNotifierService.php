<?php

namespace App\Services;

use App\Models\Property;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TeleskopNotifierService
{
    private string $apiUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->apiUrl = rtrim(config('services.teleskop.url'), '/');
        $this->apiKey = config('services.teleskop.api_key');
    }

    public function notify(Property $property): void
    {
        if (!$this->apiUrl || !$this->apiKey) return;

        $category = match((int) $property->category_id) {
            1       => 'apartment',
            2       => 'house',
            3       => 'land',
            5, 7    => 'commercial',
            default => null,
        };

        $dealType = match((int) $property->category_id) {
            1, 2, 3, 5 => 'sale',
            7, 8, 9    => 'rent',
            default    => 'sale',
        };

        try {
            Http::timeout(5)
                ->withHeader('X-Api-Key', $this->apiKey)
                ->post("{$this->apiUrl}/api/ingest/listing", [
                    'external_id' => $property->bina_id,
                    'source'      => 'binaaz',
                    'url'         => 'https://bina.az' . $property->path,
                    'title'       => $property->title,
                    'category'    => $category,
                    'deal_type'   => $dealType,
                    'price'       => (int) $property->price,
                    'currency'    => $property->currency ?? 'AZN',
                    'rooms'       => $property->rooms,
                    'area'        => $property->area,
                    'floor'       => is_numeric($property->floor) ? (int) $property->floor : null,
                    'floors'      => $property->floor_total,
                    'location'    => $property->location_full_name,
                    'photo'       => $property->photos[0]['thumb'] ?? null,
                    'listed_at'   => $property->bumped_at?->toIso8601String(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('TeleskopNotifier xətası: ' . $e->getMessage());
        }
    }
}
