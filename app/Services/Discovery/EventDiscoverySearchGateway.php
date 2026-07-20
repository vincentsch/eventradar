<?php

namespace App\Services\Discovery;

interface EventDiscoverySearchGateway
{
    /**
     * @param  list<string|list<string>>  $filters
     * @param  list<string>  $sort
     */
    public function searchIds(string $query, array $filters, array $sort, int $page, int $perPage): EventDiscoverySearchResult;
}
