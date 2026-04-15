<?php

namespace App\Services\BinaAz;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

class BinaAzClient
{
    private const BASE_URL = 'https://bina.az';
    private const GRAPHQL_URL = 'https://bina.az/graphql';
    private const TIMEOUT = 10;

    private const USER_AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:126.0) Gecko/20100101 Firefox/126.0',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36 Edg/123.0.0.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:125.0) Gecko/20100101 Firefox/125.0',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36 OPR/108.0.0.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_3) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.3 Safari/605.1.15',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
    ];

    public function graphqlGet(array $params): array
    {
        $this->waitForRateLimit('graphql');

        $response = Http::timeout(self::TIMEOUT)
            ->withHeaders($this->getHeaders())
            ->get(self::GRAPHQL_URL, $params);

        return $this->handleResponse($response);
    }

    public function graphqlPost(array $body): array
    {
        $this->waitForRateLimit('graphql');

        $response = Http::timeout(self::TIMEOUT)
            ->withHeaders($this->getHeaders())
            ->post(self::GRAPHQL_URL, $body);

        return $this->handleResponse($response);
    }

    public function fetchPage(string $path): string
    {
        $this->waitForRateLimit('page');

        $url = str_starts_with($path, 'http') ? $path : self::BASE_URL . '/' . ltrim($path, '/');

        $response = Http::timeout(self::TIMEOUT)
            ->withHeaders($this->getHeaders())
            ->get($url);

        if (!$response->successful()) {
            throw new \RuntimeException("Failed to fetch page: {$url} (HTTP {$response->status()})");
        }

        return $response->body();
    }

    private function getHeaders(): array
    {
        return [
            'User-Agent' => $this->getRandomUserAgent(),
            'Accept' => 'application/json, text/html, */*',
            'Accept-Language' => 'az,en;q=0.9,ru;q=0.8',
            'Referer' => self::BASE_URL,
        ];
    }

    private function getRandomUserAgent(): string
    {
        return self::USER_AGENTS[array_rand(self::USER_AGENTS)];
    }

    private function waitForRateLimit(string $type): void
    {
        $key = "bina_az_rate:{$type}";
        $maxAttempts = $type === 'graphql' ? 5 : 1;
        $decaySeconds = $type === 'graphql' ? 1 : 2;

        while (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            usleep(($seconds * 1000000) + random_int(100000, 500000));
        }

        RateLimiter::hit($key, $decaySeconds);
    }

    private function handleResponse($response): array
    {
        if (!$response->successful()) {
            throw new \RuntimeException("HTTP Error: {$response->status()}");
        }

        $data = $response->json();

        if (isset($data['errors'][0]['message'])) {
            throw new \RuntimeException("GraphQL Error: {$data['errors'][0]['message']}");
        }

        return $data;
    }
}
