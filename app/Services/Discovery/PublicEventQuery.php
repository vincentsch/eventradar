<?php

namespace App\Services\Discovery;

final readonly class PublicEventQuery
{
    public function __construct(
        public ?string $search,
        public ?string $type,
        public ?string $location,
        public ?string $from,
        public ?string $to,
        public ?string $cursor,
        public int $page,
    ) {}

    public function hasDiscovery(): bool
    {
        return $this->search !== null
            || $this->type !== null
            || $this->location !== null
            || $this->from !== null
            || $this->to !== null;
    }

    /** @return array{q: ?string, type: ?string, location: ?string, from: ?string, to: ?string} */
    public function canonical(): array
    {
        return [
            'q' => $this->search,
            'type' => $this->type,
            'location' => $this->location,
            'from' => $this->from,
            'to' => $this->to,
        ];
    }
}
