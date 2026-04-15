<?php

namespace App\Services\BinaAz;

class BinaAzGraphQLService
{
    private const FEATURED_HASH = 'f34b27afebc725b2bb62b62f9757e1740beaf2dc162f4194e29ba5a608b3cb41';
    private const SEARCH_HASH = '872e9c694c34b6674514d48e9dcf1b46241d3d79f365ddf20d138f18e74554c5';

    public function __construct(
        private BinaAzClient $client,
    ) {}

    /**
     * Əsas səhifədən elanları çək (offset-based pagination)
     */
    public function fetchFeatured(int $limit = 24, int $offset = 0): array
    {
        $data = $this->client->graphqlGet([
            'operationName' => 'FeaturedItemsRow',
            'variables' => json_encode(['limit' => $limit, 'offset' => $offset]),
            'extensions' => json_encode([
                'persistedQuery' => [
                    'version' => 1,
                    'sha256Hash' => self::FEATURED_HASH,
                ],
            ]),
        ]);

        $items = $data['data']['items'] ?? [];

        return [
            'items' => $items,
            'hasMore' => count($items) === $limit,
            'nextOffset' => $offset + $limit,
        ];
    }

    /**
     * Filtrli axtarış (cursor-based pagination)
     */
    public function searchItems(array $filter, int $first = 16, ?string $cursor = null): array
    {
        $data = $this->client->graphqlPost([
            'operationName' => 'SearchItems',
            'variables' => [
                'first' => $first,
                'filter' => $filter,
                'sort' => 'BUMPED_AT_DESC',
                'cursor' => $cursor,
            ],
            'extensions' => [
                'persistedQuery' => [
                    'version' => 1,
                    'sha256Hash' => self::SEARCH_HASH,
                ],
            ],
        ]);

        $connection = $data['data']['itemsConnection'] ?? [];
        $edges = $connection['edges'] ?? [];
        $pageInfo = $connection['pageInfo'] ?? [];

        return [
            'items' => array_map(fn($edge) => $edge['node'], $edges),
            'hasMore' => $pageInfo['hasNextPage'] ?? false,
            'endCursor' => $pageInfo['endCursor'] ?? null,
        ];
    }

    /**
     * Kateqoriya və lokasiyaya görə filtr qur
     */
    public function buildFilter(
        string $categoryId,
        array $locationIds = [],
        ?int $priceMin = null,
        ?int $priceMax = null,
        ?int $roomMin = null,
        ?int $roomMax = null,
        ?int $areaMin = null,
        ?int $areaMax = null,
        bool $leased = false,
        ?bool $hasRepair = null,
    ): array {
        $filter = [
            'leased' => $leased,
            'cityId' => '1', // Bakı
            'categoryId' => $categoryId,
        ];

        if (!empty($locationIds)) {
            $filter['locationIds'] = array_map('strval', $locationIds);
        }

        if ($priceMin !== null) $filter['priceFrom'] = $priceMin;
        if ($priceMax !== null) $filter['priceTo'] = $priceMax;
        if ($roomMin !== null || $roomMax !== null) {
            $rooms = [];
            $min = $roomMin ?? 1;
            $max = $roomMax ?? 10;
            for ($i = $min; $i <= $max; $i++) {
                $rooms[] = (string) $i;
            }
            $filter['roomIds'] = $rooms;
        }
        if ($areaMin !== null) $filter['areaFrom'] = $areaMin;
        if ($areaMax !== null) $filter['areaTo'] = $areaMax;
        if ($hasRepair !== null) $filter['hasRepair'] = $hasRepair;

        return $filter;
    }
}
