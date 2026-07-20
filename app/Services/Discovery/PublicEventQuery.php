<?php

namespace App\Services\Discovery;

final readonly class PublicEventQuery
{
    /**
     * @param  list<string>  $types
     * @param  list<string>  $locations
     */
    public function __construct(
        public ?string $search,
        public array $types,
        public array $locations,
        public ?string $from,
        public ?string $to,
        public bool $includeOngoing,
        public ?string $cursor,
        public int $page,
    ) {}

    public function hasDiscovery(): bool
    {
        return $this->search !== null
            || $this->types !== []
            || $this->locations !== []
            || $this->from !== null
            || $this->to !== null;
    }

    /** @return array{q: ?string, type: list<string>, location: list<string>, from: ?string, to: ?string, ongoing: bool} */
    public function canonical(): array
    {
        return [
            'q' => $this->search,
            'type' => $this->types,
            'location' => $this->locations,
            'from' => $this->from,
            'to' => $this->to,
            'ongoing' => $this->includeOngoing,
        ];
    }
}
