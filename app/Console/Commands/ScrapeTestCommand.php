<?php

namespace App\Console\Commands;

use App\Services\BinaAz\BinaAzGraphQLService;
use App\Services\BinaAz\BinaAzOwnerChecker;
use App\Services\BinaAz\BinaAzPropertyParser;
use Illuminate\Console\Command;

class ScrapeTestCommand extends Command
{
    protected $signature = 'scrape:test {--category=2 : bina.az category ID} {--limit=5 : Max items to fetch}';
    protected $description = 'bina.az-dan test scraping et (owner check ilə)';

    public function handle(BinaAzGraphQLService $graphql, BinaAzPropertyParser $parser, BinaAzOwnerChecker $checker): int
    {
        $categoryId = $this->option('category');
        $limit = (int) $this->option('limit');

        $this->info("Scraping category {$categoryId}, limit {$limit}...");

        try {
            $filter = $graphql->buildFilter(categoryId: $categoryId);
            $result = $graphql->searchItems($filter, $limit);

            $this->info("Got " . count($result['items']) . " items");

            $saved = 0;
            $notOwner = 0;
            $biz = 0;

            foreach ($result['items'] as $node) {
                if (!empty($node['isBusiness'])) {
                    $biz++;
                    continue;
                }

                $binaId = $node['id'] ?? null;
                $path = $node['path'] ?? "/items/{$binaId}";
                $isOwner = $checker->checkByPath($path);

                if ($isOwner) {
                    $property = $parser->parseAndUpsert($node, $categoryId);
                    if (!$property) continue;
                    $saved++;
                    $this->line("[MÜLKİYYƏTÇİ] {$property->bina_id} | {$property->price} {$property->currency} | {$property->rooms} otaq | {$property->location_full_name}");
                } else {
                    $notOwner++;
                }
            }

            $this->info("Nəticə: {$saved} mülkiyyətçi, {$notOwner} vasitəçi, {$biz} business");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Error: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
