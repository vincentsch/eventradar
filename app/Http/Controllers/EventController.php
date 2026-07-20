<?php

namespace App\Http\Controllers;

use App\Domain\Events\EventStatus;
use App\Models\Event;
use DateTimeImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    /** @var list<string> */
    private const LIST_COLUMNS = [
        'id',
        'title',
        'type',
        'status',
        'starts_at',
        'starts_on_local',
        'timezone',
        'venue_name',
        'locality',
        'region',
        'country',
        'country_code',
    ];

    public function index(Request $request): Response
    {
        return Inertia::render('Events/Index', [
            'filters' => $this->filters($request),
            'statuses' => EventStatus::publicValues(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        [$events, $stats] = $this->loadListing($request);

        return response()->json([
            'data' => $events->items(),
            'current_page' => $events->currentPage(),
            'last_page' => $events->lastPage(),
            'total' => $events->total(),
            'stats' => $stats,
        ]);
    }

    public function show(string $event): Response
    {
        $record = Event::query()
            ->select([
                'id',
                'title',
                'description',
                'organizer_name',
                'venue_name',
                'starts_at',
                'ends_at',
                'timezone',
                'starts_on_local',
                'locality',
                'region',
                'country',
                'country_code',
                'latitude',
                'longitude',
                'image_set_key',
                'status',
                'type',
                'tags',
                'minimum_price',
                'currency_code',
                'capacity',
            ])
            ->with(['imageSet.images' => fn ($query) => $query
                ->select(['id', 'image_set_key', 'role', 'path', 'width', 'height', 'alt'])
                ->orderBy('role')])
            ->whereKey($event)
            ->whereIn('status', EventStatus::publicValues())
            ->where('ends_at', '>', Date::now('UTC'))
            ->firstOrFail();

        return Inertia::render('Events/Show', [
            'event' => [
                ...$record->only([
                    'id',
                    'title',
                    'description',
                    'organizer_name',
                    'venue_name',
                    'starts_at',
                    'ends_at',
                    'timezone',
                    'starts_on_local',
                    'locality',
                    'region',
                    'country',
                    'country_code',
                    'latitude',
                    'longitude',
                    'status',
                    'type',
                    'tags',
                    'minimum_price',
                    'currency_code',
                    'capacity',
                ]),
                'images' => $record->imageSet?->images->map(fn ($image): array => $image->only([
                    'role',
                    'path',
                    'width',
                    'height',
                    'alt',
                ]))->values()->all() ?? [],
            ],
        ]);
    }

    /**
     * @return array{0: LengthAwarePaginator<int, Event>, 1: array{ms: int, bytes: int}}
     */
    private function loadListing(Request $request): array
    {
        $start = microtime(true);
        $filters = $this->filters($request);

        $events = Event::query()
            ->select(self::LIST_COLUMNS)
            ->whereIn('status', EventStatus::publicValues())
            ->where('ends_at', '>', Date::now('UTC'))
            ->when($filters['status'] !== null, fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['from'] !== null, fn (Builder $query) => $query->where('starts_on_local', '>=', $filters['from']))
            ->orderBy('starts_at')
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString();

        $stats = [
            'ms' => (int) round((microtime(true) - $start) * 1000),
            'bytes' => strlen((string) json_encode($events->items())),
        ];

        return [$events, $stats];
    }

    /** @return array{status: ?string, from: ?string} */
    private function filters(Request $request): array
    {
        $status = $request->string('status')->trim()->toString();
        $from = $request->string('from')->trim()->toString();

        return [
            'status' => in_array($status, EventStatus::publicValues(), true) ? $status : null,
            'from' => $this->isValidDate($from) ? $from : null,
        ];
    }

    private function isValidDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
