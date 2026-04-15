<?php

namespace App\Services\BinaAz;

use App\Models\Property;

class BinaAzOwnerChecker
{
    public function __construct(
        private BinaAzClient $client,
    ) {}

    /**
     * Property model ilə yoxla + DB-yə yaz
     */
    public function check(Property $property): bool
    {
        try {
            $isOwner = $this->checkByPath($property->path);

            $property->update([
                'is_owner' => $isOwner,
                'owner_checked_at' => now(),
            ]);

            return $isOwner;
        } catch (\Throwable $e) {
            report($e);
            return false;
        }
    }

    /**
     * Sadəcə path ilə yoxla — DB-yə yazmır
     */
    public function checkByPath(string $path): bool
    {
        try {
            $html = $this->client->fetchPage($path);
            return $this->parseOwnerStatus($html);
        } catch (\Throwable $e) {
            report($e);
            return false;
        }
    }

    private function parseOwnerStatus(string $html): bool
    {
        // Pattern 1: product-owner__info-region div
        if (preg_match('/<div class="product-owner__info-region"[^>]*>(.*?)<\/div>/', $html, $matches)) {
            $region = trim(strip_tags($matches[1]));
            if (mb_strtolower($region) === 'mülkiyyətçi') {
                return true;
            }
        }

        // Pattern 2: data-cy="owner-info" div
        if (preg_match('/<div[^>]*data-cy="owner-info"[^>]*>(.*?)<\/div>/s', $html, $matches)) {
            if (str_contains($matches[1], 'Mülkiyyətçi')) {
                return true;
            }
        }

        return false;
    }
}
