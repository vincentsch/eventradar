<?php

namespace App\Services\Discovery;

use Meilisearch\Client;

final class MeilisearchEventDiscoverySearchGateway implements EventDiscoverySearchGateway
{
    public function __construct(private readonly Client $client) {}

    public function searchIds(string $query, array $filters, array $sort, int $page, int $perPage): EventDiscoverySearchResult
    {
        $result = $this->client
            ->index((string) config('meilisearch.event_index', 'events_v1'))
            ->search($query, [
                'attributesToRetrieve' => ['id'],
                'filter' => $filters,
                'sort' => $sort,
                'page' => $page,
                'hitsPerPage' => $perPage,
            ]);

        $ids = [];
        foreach ($result->getHits() as $hit) {
            if (isset($hit['id']) && is_string($hit['id'])) {
                $ids[] = $hit['id'];
            }
        }

        return new EventDiscoverySearchResult(
            ids: $ids,
            total: $result->getTotalHits() ?? 0,
            page: $result->getPage() ?? $page,
            processingTimeMs: $result->getProcessingTimeMs(),
        );
    }
}
