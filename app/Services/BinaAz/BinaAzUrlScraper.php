<?php

namespace App\Services\BinaAz;

use App\Models\Property;
use Illuminate\Support\Facades\Http;

class BinaAzUrlScraper
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';

    public function scrape(string $url): array
    {
        // bina.az URL-dən numeric ID çıxar, öz DB-mizdə axtar
        $binaId = $this->extractBinaId($url);
        if ($binaId) {
            $local = Property::where('bina_id', $binaId)->first();
            if ($local) return $this->fromLocalProperty($local, $url);
        }

        $html = $this->fetchHtml($url);
        return $this->parseHtml($html, $url);
    }

    private function extractBinaId(string $url): ?string
    {
        if (preg_match('/\/(\d{6,12})\/?(?:\?.*)?$/', $url, $m)) {
            return $m[1];
        }
        return null;
    }

    private function fromLocalProperty(Property $prop, string $url): array
    {
        return array_filter([
            'title'       => $prop->title,
            'price'       => $prop->price,
            'currency'    => $prop->currency ?? 'AZN',
            'rooms'       => $prop->rooms,
            'area'        => $prop->area,
            'floor'       => $prop->floor ? (int) $prop->floor : null,
            'floor_total' => $prop->floor_total,
            'location_id' => $prop->location_id,
            'category_id' => $prop->category_id,
            'photos'      => $prop->photos ?: [],
            'bina_url'    => $url,
        ], fn($v) => $v !== null && $v !== []);
    }

    private function fetchHtml(string $url): string
    {
        $response = Http::timeout(12)
            ->withHeaders([
                'User-Agent'      => self::USER_AGENT,
                'Accept'          => 'text/html,application/xhtml+xml,*/*',
                'Accept-Language' => 'az,en;q=0.9',
                'Referer'         => 'https://bina.az/',
            ])
            ->get($url);

        if (!$response->successful()) {
            throw new \RuntimeException("Səhifə yüklənmədi (HTTP {$response->status()})");
        }

        return $response->body();
    }

    private function parseHtml(string $html, string $url): array
    {
        $result = ['bina_url' => $url];

        // ── 1. JSON-LD (ən dəqiq mənbə) ─────────────────────────────
        if (preg_match('/<script[^>]*type="application\/ld\+json"[^>]*>(.+?)<\/script>/s', $html, $m)) {
            $ld = json_decode($m[1], true);
            $graph = $ld['@graph'] ?? [$ld];

            foreach ($graph as $node) {
                if (($node['@type'] ?? '') === 'Product') {
                    $result['title']    = $node['name'] ?? null;
                    $result['price']    = $node['offers']['price'] ?? null;
                    $result['currency'] = $node['offers']['priceCurrency'] ?? 'AZN';

                    $offered = $node['offers']['itemOffered'] ?? [];
                    $result['rooms'] = $offered['numberOfRooms'] ?? null;
                    $result['area']  = $offered['floorSize']['value'] ?? null;

                    // Photos from JSON-LD image array
                    if (!empty($node['image'])) {
                        $result['photos'] = array_map(
                            fn($img) => ['medium' => $img, 'thumb' => $img],
                            array_slice((array) $node['image'], 0, 10)
                        );
                    }
                    break;
                }
            }
        }

        // ── 2. Meta tags (price fallback) ────────────────────────────
        if (empty($result['price'])) {
            if (preg_match('/<meta property="product:price:amount" content="([\d.]+)"/i', $html, $m)) {
                $result['price'] = (float) $m[1];
            }
        }
        if (empty($result['currency'])) {
            if (preg_match('/<meta property="product:price:currency" content="([^"]+)"/i', $html, $m)) {
                $result['currency'] = $m[1];
            }
        }

        // ── 3. Floor — HTML-dən çıxar: "3 / 6" ──────────────────────
        if (preg_match('/Mərtəbə<\/label><span[^>]*>([\d]+)\s*\/\s*([\d]+)/u', $html, $m)) {
            $result['floor']       = (int) $m[1];
            $result['floor_total'] = (int) $m[2];
        }

        // ── 4. Rooms fallback (HTML) ──────────────────────────────────
        if (empty($result['rooms'])) {
            if (preg_match('/Otaq sayı<\/label><span[^>]*>(\d+)/u', $html, $m)) {
                $result['rooms'] = (int) $m[1];
            }
        }

        // ── 5. Area fallback (HTML) ───────────────────────────────────
        if (empty($result['area'])) {
            if (preg_match('/Sahə<\/label><span[^>]*>([\d.]+)\s*m²/u', $html, $m)) {
                $result['area'] = (float) $m[1];
            }
        }

        // ── 6. Title fallback from og:title ──────────────────────────
        if (empty($result['title'])) {
            if (preg_match('/<meta property="og:title" content="([^"]+)"/i', $html, $m)) {
                $result['title'] = html_entity_decode($m[1]);
            }
        }

        return array_filter($result, fn($v) => $v !== null && $v !== '' && $v !== []);
    }
}
