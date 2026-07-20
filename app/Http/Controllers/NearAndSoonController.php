<?php

namespace App\Http\Controllers;

use App\Http\Requests\Public\NearAndSoonRequest;
use App\Services\Discovery\PublicEventFilterOptions;
use App\Services\Discovery\PublicEventMapSearch;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Meilisearch\Exceptions\ExceptionInterface;

class NearAndSoonController extends Controller
{
    public function __invoke(
        NearAndSoonRequest $request,
        PublicEventMapSearch $search,
        PublicEventFilterOptions $filterOptions,
    ): Response {
        $criteria = $request->criteria();
        $status = 'ready';

        try {
            $result = $search->search($criteria, $request->instant());
        } catch (ExceptionInterface $exception) {
            Log::warning('Public event map discovery is unavailable.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            $result = ['events' => [], 'provider_count' => 0, 'processing_time_ms' => 0];
            $status = 'unavailable';
        }

        return Inertia::render('Public/NearAndSoon', [
            'events' => $result['events'],
            'query' => $criteria,
            'discovery' => [
                'status' => $status,
                'providerCount' => $result['provider_count'],
                'totalCountIsCapped' => $result['provider_count'] >= (int) config('meilisearch.pagination_max_total_hits'),
                'hydratedCount' => count($result['events']),
                'processingTimeMs' => $result['processing_time_ms'],
                'limit' => PublicEventMapSearch::RESULT_LIMIT,
            ],
            'filters' => $filterOptions->all($request->instant()),
        ]);
    }
}
