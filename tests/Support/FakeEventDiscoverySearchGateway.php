<?php

namespace Tests\Support;

use App\Services\Discovery\EventDiscoverySearchGateway;
use App\Services\Discovery\EventDiscoverySearchResult;
use Meilisearch\Exceptions\ExceptionInterface;

final class FakeEventDiscoverySearchGateway implements EventDiscoverySearchGateway
{
    /** @var list<string> */
    public array $ids = [];

    public int $total = 0;

    public int $processingTimeMs = 3;

    public ?ExceptionInterface $exception = null;

    /** @var list<array{query: string, filters: list<string|list<string>>, sort: list<string>, page: int, per_page: int}> */
    public array $requests = [];

    public function searchIds(string $query, array $filters, array $sort, int $page, int $perPage): EventDiscoverySearchResult
    {
        $this->requests[] = [
            'query' => $query,
            'filters' => $filters,
            'sort' => $sort,
            'page' => $page,
            'per_page' => $perPage,
        ];

        if ($this->exception !== null) {
            throw $this->exception;
        }

        return new EventDiscoverySearchResult(
            ids: $this->ids,
            total: $this->total,
            page: $page,
            processingTimeMs: $this->processingTimeMs,
        );
    }
}
