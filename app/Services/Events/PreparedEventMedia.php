<?php

namespace App\Services\Events;

final readonly class PreparedEventMedia
{
    /**
     * @param  list<array<string, int|string>>  $rows
     * @param  list<string>  $paths
     */
    public function __construct(
        public array $rows,
        public array $paths,
    ) {}
}
