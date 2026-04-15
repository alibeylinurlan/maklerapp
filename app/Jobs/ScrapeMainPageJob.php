<?php

namespace App\Jobs;

use App\Models\PriceHistory;
use App\Models\Property;
use App\Services\BinaAz\BinaAzGraphQLService;
use App\Services\BinaAz\BinaAzPropertyParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ScrapeMainPageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 15;

    public function __construct()
    {
        $this->onQueue('scraping');
    }

    public function handle(BinaAzGraphQLService $graphql, BinaAzPropertyParser $parser): void
    {
        // Main page-dən son 16 elanı çək (FeaturedItemsRow)
        $result = $graphql->fetchFeatured(limit: 16, offset: 0);

        $newCount = 0;
        $updatedCount = 0;
        $skipped = 0;

        foreach ($result['items'] as $node) {
            // Business elanları skip
            if (!empty($node['isBusiness'])) {
                $skipped++;
                continue;
            }

            $binaId = $node['id'] ?? null;
            if (!$binaId) continue;

            $existing = Property::where('bina_id', $binaId)->first();

            if ($existing) {
                // Mövcud elan — yenilənmə vaxtı yarım saatdan çoxdursa update et
                $nodeBumpedAt = isset($node['updatedAt']) ? \Carbon\Carbon::parse($node['updatedAt']) : null;

                if ($nodeBumpedAt && $existing->bumped_at && $nodeBumpedAt->gt($existing->bumped_at->addMinutes(30))) {
                    $newPrice = $node['price']['value'] ?? null;
                    $oldPrice = $existing->price;

                    // Qiymət dəyişibsə tarixi saxla
                    if ($newPrice && $oldPrice && abs($newPrice - $oldPrice) > 0.01) {
                        PriceHistory::create([
                            'property_id' => $existing->id,
                            'price' => $oldPrice,
                            'currency' => $existing->currency,
                            'recorded_at' => $existing->bumped_at,
                        ]);
                    }

                    // Elanı yenilə
                    $parser->parseAndUpsert($node);
                    $updatedCount++;
                }
            } else {
                // Yeni elan — DB-yə yaz və owner check et
                $property = $parser->parseAndUpsert($node);
                if ($property) {
                    $newCount++;
                    CheckOwnershipJob::dispatch($property->id);
                }
            }
        }

        Log::info("MainPage scrape: {$newCount} new, {$updatedCount} updated, {$skipped} biz skipped");

        // Növbəti scrape üçün random gözləmə (3-5 dəq arası)
        $delaySeconds = random_int(180, 300);
        Cache::put('scrape_main_running', true, $delaySeconds + 120);
        self::dispatch()->delay(now()->addSeconds($delaySeconds));
    }
}
