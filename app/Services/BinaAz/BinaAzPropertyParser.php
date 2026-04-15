<?php

namespace App\Services\BinaAz;

use App\Models\Category;
use App\Models\Location;
use App\Models\Property;
use Illuminate\Support\Facades\Cache;

class BinaAzPropertyParser
{
    /**
     * @param string|null $categoryBinaId — sorğu göndərdiyimiz kateqoriya ID-si
     */
    public function parseAndUpsert(array $node, ?string $categoryBinaId = null): ?Property
    {
        $binaId = $node['id'] ?? null;
        if (!$binaId) return null;

        // 3 aydan köhnə elanları skip et
        $updatedAt = $node['updatedAt'] ?? null;
        if ($updatedAt && now()->diffInMonths(\Carbon\Carbon::parse($updatedAt)) >= 3) {
            return null;
        }

        $isNew = !Property::where('bina_id', $binaId)->exists();

        $property = Property::updateOrCreate(
            ['bina_id' => $binaId],
            [
                'category_id' => $this->resolveCategoryId($node, $categoryBinaId),
                'title' => $this->extractTitle($node),
                'price' => $node['price']['total'] ?? $node['price']['value'] ?? null,
                'currency' => $node['price']['currency'] ?? 'AZN',
                'area' => $node['area']['value'] ?? null,
                'rooms' => $node['rooms'] ?? null,
                'floor' => isset($node['floor']) ? (string) $node['floor'] : null,
                'floor_total' => $node['floors'] ?? null,
                'location_full_name' => $node['location']['fullName'] ?? null,
                'location_id' => $this->resolveLocationId($node),
                'path' => $node['path'] ?? '',
                'photos' => $this->extractPhotos($node),
                'has_mortgage' => $node['hasMortgage'] ?? false,
                'has_repair' => $node['hasRepair'] ?? false,
                'has_bill_of_sale' => $node['hasBillOfSale'] ?? false,
                'is_leased' => $node['leased'] ?? false,
                'is_business' => $node['isBusiness'] ?? false,
                'is_vipped' => $node['vipped'] ?? false,
                'is_featured' => $node['featured'] ?? false,
                'is_owner' => true,
                'owner_checked_at' => now(),
                'bumped_at' => $node['updatedAt'] ?? null,
                'first_seen_at' => $isNew ? now() : Property::where('bina_id', $binaId)->value('first_seen_at'),
            ]
        );

        $property->_is_new = $isNew;

        return $property;
    }

    private function extractTitle(array $node): ?string
    {
        $parts = [];
        if (isset($node['rooms'])) $parts[] = $node['rooms'] . ' otaq';
        $area = $node['area']['value'] ?? null;
        if ($area) $parts[] = $area . ' m²';
        if (isset($node['location']['fullName'])) $parts[] = $node['location']['fullName'];
        return implode(', ', $parts) ?: null;
    }

    private function extractPhotos(array $node): array
    {
        $photos = [];
        foreach ($node['photos'] ?? [] as $photo) {
            $item = [];
            if (isset($photo['f460x345'])) $item['medium'] = $photo['f460x345'];
            if (isset($photo['thumbnail'])) $item['thumb'] = $photo['thumbnail'];
            if (isset($photo['large'])) $item['large'] = $photo['large'];
            if (!empty($item)) $photos[] = $item;
        }
        return $photos;
    }

    private function resolveCategoryId(array $node, ?string $categoryBinaId): ?int
    {
        // 1. Sorğudan gələn kateqoriya ID
        // 2. Node-dan gələn (əgər varsa)
        $binaId = $categoryBinaId ?? $node['categoryId'] ?? $node['category']['id'] ?? null;
        if (!$binaId) return null;

        return Cache::remember("cat_resolve:{$binaId}", 3600, function () use ($binaId) {
            return Category::where('bina_id', (string) $binaId)->value('id');
        });
    }

    private function resolveLocationId(array $node): ?int
    {
        $locationBinaId = $node['location']['id'] ?? null;
        if (!$locationBinaId) return null;

        $fullName = $node['location']['fullName'] ?? $node['location']['name'] ?? null;

        return Cache::remember("loc_resolve:{$locationBinaId}", 3600, function () use ($locationBinaId, $fullName) {
            $id = Location::where('bina_id', (string) $locationBinaId)->value('id');

            // Tapılmadısa — yeni location yarat
            if (!$id && $fullName) {
                $loc = Location::create([
                    'bina_id' => (string) $locationBinaId,
                    'slug' => \Illuminate\Support\Str::slug($fullName),
                    'name_az' => $fullName,
                ]);
                $id = $loc->id;
            }

            return $id;
        });
    }
}
