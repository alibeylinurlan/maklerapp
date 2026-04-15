<?php

namespace App\Console\Commands;

use App\Jobs\MatchNewPropertyJob;
use App\Models\PriceHistory;
use App\Models\Property;
use App\Services\BinaAz\BinaAzGraphQLService;
use App\Services\BinaAz\BinaAzOwnerChecker;
use App\Services\BinaAz\BinaAzPropertyParser;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class ScrapeLoopCommand extends Command
{
    protected $signature = 'scrape:loop';
    protected $description = 'Daimi main page scraper — hər 5-10 san bir bina.az yoxlayır';

    private bool $running = true;
    private int $categoryIndex = 0;
    private array $categoryIds = ['1', '2', '3', '5', '7', '8', '9', '10'];

    public function handle(
        BinaAzGraphQLService $graphql,
        BinaAzPropertyParser $parser,
        BinaAzOwnerChecker $ownerChecker,
    ): int {
        $this->info('Scrape loop başladı...');

        // Graceful shutdown
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, fn() => $this->running = false);
            pcntl_signal(SIGINT, fn() => $this->running = false);
        }

        while ($this->running) {
            try {
                $this->tick($graphql, $parser, $ownerChecker);
            } catch (\Throwable $e) {
                $this->error("Xəta: {$e->getMessage()}");
                sleep(15); // xəta olsa biraz daha gözlə
            }

            $delay = random_int(5, 10);
            sleep($delay);
        }

        $this->info('Scrape loop dayandı.');
        return Command::SUCCESS;
    }

    private function tick(
        BinaAzGraphQLService $graphql,
        BinaAzPropertyParser $parser,
        BinaAzOwnerChecker $ownerChecker,
    ): void {
        // Növbə ilə kateqoriyaları gəz (round-robin)
        $categoryId = $this->categoryIds[$this->categoryIndex];
        $this->categoryIndex = ($this->categoryIndex + 1) % count($this->categoryIds);

        $filter = $graphql->buildFilter(categoryId: $categoryId);

        // İlk səhifə
        $result = $graphql->searchItems($filter, 24);

        // 2-ci səhifə də çək (daha çox elan tutmaq üçün)
        if ($result['hasMore'] && $result['endCursor']) {
            sleep(1);
            $page2 = $graphql->searchItems($filter, 24, $result['endCursor']);
            $result['items'] = array_merge($result['items'], $page2['items']);
        }

        $new = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($result['items'] as $node) {
            if (!empty($node['isBusiness'])) {
                $skipped++;
                continue;
            }

            $binaId = $node['id'] ?? null;
            if (!$binaId) continue;

            $existing = Property::where('bina_id', $binaId)->first();

            if (!$existing) {
                // Əvvəl vasitəçi kimi yoxlanılıb? Redis-dən bax
                if (Cache::has("not_owner:{$binaId}")) {
                    $skipped++;
                    continue;
                }

                // Əvvəlcə owner check — yalnız mülkiyyətçidirsə DB-yə yaz
                $path = $node['path'] ?? "/items/{$binaId}";
                $isOwner = $ownerChecker->checkByPath($path);

                if ($isOwner) {
                    $property = $parser->parseAndUpsert($node, $categoryId);
                    if (!$property) continue;
                    $new++;
                    $this->line("[YENİ] {$binaId} | {$property->price} {$property->currency} | {$property->rooms} otaq | {$property->location_full_name}");
                    $this->publishToSocket($property);
                    MatchNewPropertyJob::dispatch($property->id);
                } else {
                    Cache::put("not_owner:{$binaId}", true, 86400);
                }
            } else {
                // Mövcud elanbu — yalnız is_owner=true olanları update et
                if ($existing->is_owner !== true) {
                    $existing->delete();
                    Cache::put("not_owner:{$binaId}", true, 86400);
                    $skipped++;
                    continue;
                }

                // Yenilənib?
                $nodeBumpedAt = isset($node['updatedAt']) ? Carbon::parse($node['updatedAt']) : null;

                if ($nodeBumpedAt && $existing->bumped_at && $nodeBumpedAt->gt($existing->bumped_at->addMinutes(30))) {
                    $newPrice = $node['price']['total'] ?? $node['price']['value'] ?? null;
                    $oldPrice = (float) $existing->price;

                    // Qiymət dəyişibsə tarixi saxla
                    if ($newPrice && $oldPrice && abs($newPrice - $oldPrice) > 0.01) {
                        PriceHistory::create([
                            'property_id' => $existing->id,
                            'price' => $oldPrice,
                            'currency' => $existing->currency,
                            'recorded_at' => $existing->bumped_at,
                        ]);
                        $this->line("[QİYMƏT] {$binaId} | {$oldPrice} → {$newPrice} {$existing->currency}");
                    }

                    $property = $parser->parseAndUpsert($node, $categoryId);
                    if ($property) $this->publishToSocket($property);
                    $updated++;
                } else {
                    $skipped++;
                }
            }
        }

        $total = count($result['items']);
        $ownerFailed = $total - $new - $updated - $skipped;
        $this->line("[" . now()->format('H:i:s') . "] cat:{$categoryId} | {$total} elan | {$new} yeni, {$updated} upd, {$skipped} skip, {$ownerFailed} vasitəçi");
    }

    private function publishToSocket(Property $property): void
    {
        try {
            $thumb = null;
            if (!empty($property->photos)) {
                $thumb = $property->photos[0]['thumb'] ?? $property->photos[0]['medium'] ?? null;
            }
            Redis::publish('properties.new', json_encode([
                'id'          => $property->id,
                'price'       => $property->price
                    ? number_format($property->price) . ' ' . ($property->currency === 'azn' ? '₼' : ($property->currency === 'AZN' ? '₼' : '$'))
                    : null,
                'rooms'       => $property->rooms,
                'area'        => $property->area,
                'floor'       => $property->floor,
                'floor_total' => $property->floor_total,
                'location'    => $property->location_full_name,
                'category'    => $property->category?->name_az,
                'thumb'       => $thumb,
                'url'         => $property->full_url,
                'at'          => now()->diffForHumans(),
            ]));
        } catch (\Throwable $e) {
            $this->error("Socket publish xətası: {$e->getMessage()}");
        }
    }
}
