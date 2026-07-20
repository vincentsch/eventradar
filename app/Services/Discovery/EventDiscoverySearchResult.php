<?php

namespace App\Services\Discovery;

final readonly class EventDiscoverySearchResult
{
    /** @param list<string> $ids */
    public function __construct(
        public array $ids,
        public int $total,
        public int $page,
        public int $processingTimeMs,
    ) {}
}
