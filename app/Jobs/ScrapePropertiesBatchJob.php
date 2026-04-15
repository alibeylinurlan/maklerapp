<?php

namespace App\Jobs;

use App\Services\BinaAz\BinaAzGraphQLService;
use App\Services\BinaAz\BinaAzPropertyParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScrapePropertiesBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        private string $categoryBinaId,
        private array $locationIds = [],
        private ?string $cursor = null,
        private int $pagesLeft = 5,
    ) {
        $this->onQueue('scraping');
    }

    public function handle(BinaAzGraphQLService $graphql, BinaAzPropertyParser $parser): void
    {
        $filter = $graphql->buildFilter(
            categoryId: $this->categoryBinaId,
            locationIds: $this->locationIds,
        );

        $result = $graphql->searchItems($filter, 24, $this->cursor);

        $newCount = 0;
        $existingCount = 0;
        $skippedBusiness = 0;

        foreach ($result['items'] as $node) {
            if (!empty($node['isBusiness'])) {
                $skippedBusiness++;
                continue;
            }

            $property = $parser->parseAndUpsert($node);
            if ($property && $property->_is_new) {
                $newCount++;
                CheckOwnershipJob::dispatch($property->id);
            } else {
                $existingCount++;
            }
        }

        Log::info("Scraped cat:{$this->categoryBinaId} page:" . (6 - $this->pagesLeft) . " — {$newCount} new, {$existingCount} existing, {$skippedBusiness} biz");

        // Əgər yarıdan çoxu artıq DB-dədirsə, daha dərinə getməyə ehtiyac yoxdur
        $shouldContinue = $newCount > 0 && $existingCount < count($result['items']) * 0.8;

        if ($result['hasMore'] && $this->pagesLeft > 1 && $shouldContinue) {
            self::dispatch(
                $this->categoryBinaId,
                $this->locationIds,
                $result['endCursor'],
                $this->pagesLeft - 1,
            );
        }
    }
}
