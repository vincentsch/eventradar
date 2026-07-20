<?php

namespace App\Services\Discovery;

use Inertia\ScrollMetadata;

final readonly class PublicEventFeedPage
{
    /**
     * @param  list<array<string, mixed>>  $events
     */
    public function __construct(
        public array $events,
        public ?string $previousCursor,
        public ?string $nextCursor,
        public int|string $currentCursor,
    ) {}

    /** @return array{data: list<array<string, mixed>>} */
    public function payload(): array
    {
        return ['data' => $this->events];
    }

    public function scrollMetadata(): ScrollMetadata
    {
        return new ScrollMetadata(
            pageName: 'cursor',
            previousPage: $this->previousCursor,
            nextPage: $this->nextCursor,
            currentPage: $this->currentCursor,
        );
    }
}
