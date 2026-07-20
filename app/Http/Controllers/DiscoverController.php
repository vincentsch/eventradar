<?php

namespace App\Http\Controllers;

use App\Http\Requests\Public\DiscoverEventsRequest;
use App\Services\Discovery\PublicEventFeed;
use App\Services\Discovery\PublicEventFilterOptions;
use App\Services\Discovery\PublicEventSearch;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Meilisearch\Exceptions\ExceptionInterface;

class DiscoverController extends Controller
{
    public function __invoke(
        DiscoverEventsRequest $request,
        PublicEventFeed $feed,
        PublicEventSearch $search,
        PublicEventFilterOptions $filterOptions,
    ): Response {
        $query = $request->queryValue();
        $instant = $request->instant();
        $scrollMetadata = null;
        $discovery = [
            'mode' => $query->hasDiscovery() ? 'search' : 'feed',
            'status' => 'ready',
            'providerCount' => null,
            'hydratedCount' => null,
            'processingTimeMs' => null,
        ];

        if ($query->hasDiscovery()) {
            try {
                $result = $search->page($query, $instant, $request->url());
                $events = $result['paginator'];
                $discovery['providerCount'] = $result['provider_count'];
                $discovery['hydratedCount'] = $result['hydrated_count'];
                $discovery['processingTimeMs'] = $result['processing_time_ms'];
            } catch (ExceptionInterface $exception) {
                Log::warning('Public event discovery is unavailable.', [
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
                $events = new LengthAwarePaginator([], 0, PublicEventSearch::PAGE_SIZE, 1, [
                    'path' => $request->url(),
                    'pageName' => 'page',
                ]);
                $discovery['status'] = 'unavailable';
                $discovery['providerCount'] = 0;
                $discovery['hydratedCount'] = 0;
            }
        } else {
            $feedPage = $feed->page($instant, $query->cursor);
            $events = $feedPage->payload();
            $scrollMetadata = $feedPage->scrollMetadata();
        }

        return Inertia::render('Public/Discover', [
            'events' => Inertia::scroll($events, metadata: $scrollMetadata),
            'query' => $query->canonical(),
            'discovery' => $discovery,
            'filters' => $filterOptions->all($instant),
        ]);
    }
}
