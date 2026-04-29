<?php

namespace App\Services\BinaAz;

class BinaAzGraphQLService
{
    private const SEARCH_QUERY = 'query SearchItems($first: Int, $cursor: String, $filter: ItemFilter, $sort: ItemConnectionSort!) { itemsConnection(first: $first, after: $cursor, filter: $filter, sort: $sort) { edges { node { id path price { value currency } rooms area { value } floor floors location { id name fullName } photos { thumbnail f460x345 } updatedAt hasMortgage hasRepair hasBillOfSale leased isBusiness vipped featured } } pageInfo { hasNextPage endCursor } } }';

    public function __construct(
        private BinaAzClient $client,
    ) {}

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
            'query' => self::SEARCH_QUERY,
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
