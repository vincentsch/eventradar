<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Events\EventStatus;
use App\Domain\Events\EventType;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventAttendance;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    private const PER_PAGE = 50;

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
        $countries = $this->countries();
        $filters = $this->filters($request, array_column($countries, 'code'));
        $events = Event::query()
            ->select(self::LIST_COLUMNS)
            ->when($filters['status'] !== null, fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['type'] !== null, fn (Builder $query) => $query->where('type', $filters['type']))
            ->when($filters['country_code'] !== null, fn (Builder $query) => $query->where('country_code', $filters['country_code']))
            ->when($filters['from'] !== null, fn (Builder $query) => $query->where('starts_on_local', '>=', $filters['from']))
            ->when($filters['to'] !== null, fn (Builder $query) => $query->where('starts_on_local', '<=', $filters['to']))
            ->when($filters['q'] !== null, function (Builder $query) use ($filters): void {
                if (Str::isUuid($filters['q'])) {
                    $query->whereKey($filters['q']);

                    return;
                }

                $query->where('title', 'like', $filters['q'].'%');
            })
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->appends(array_filter($filters, fn (?string $value): bool => $value !== null));

        return Inertia::render('Admin/Events/Index', [
            'events' => $events,
            'filters' => $filters,
            'options' => [
                'statuses' => EventStatus::values(),
                'types' => EventType::values(),
                'countries' => $countries,
            ],
        ]);
    }

    public function show(string $event): Response
    {
        $query = Event::query()->select([
            'id',
            'title',
            'description',
            'organizer_name',
            'venue_name',
            'starts_at',
            'ends_at',
            'timezone',
            'starts_on_local',
            'location_key',
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
            'created_at',
            'updated_at',
        ]);

        if (DB::connection()->getDriverName() === 'mysql') {
            $query->selectRaw('OCTET_LENGTH(payload) AS payload_bytes');
        } else {
            $query->selectRaw('length(CAST(payload AS BLOB)) AS payload_bytes');
        }

        $record = $query
            ->with(['imageSet.images' => fn ($query) => $query
                ->select(['id', 'image_set_key', 'role', 'path', 'width', 'height', 'alt'])
                ->orderBy('role')])
            ->whereKey($event)
            ->firstOrFail();

        $attendanceCounts = EventAttendance::query()
            ->selectRaw('intent, COUNT(*) AS aggregate')
            ->where('event_id', $record->id)
            ->whereNull('cancelled_at')
            ->groupBy('intent')
            ->pluck('aggregate', 'intent')
            ->map(fn (int|string $count): int => (int) $count);

        return Inertia::render('Admin/Events/Show', [
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
                    'location_key',
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
                    'created_at',
                    'updated_at',
                ]),
                'payload_bytes' => (int) $record->getAttribute('payload_bytes'),
                'images' => $record->imageSet?->images->map(fn ($image): array => $image->only([
                    'role',
                    'path',
                    'width',
                    'height',
                    'alt',
                ]))->values()->all() ?? [],
            ],
            'attendance' => [
                'going' => $attendanceCounts->get('going', 0),
                'interested' => $attendanceCounts->get('interested', 0),
                'total' => $attendanceCounts->sum(),
            ],
        ]);
    }

    /**
     * @param  list<string>  $countryCodes
     * @return array{q: ?string, status: ?string, type: ?string, country_code: ?string, from: ?string, to: ?string}
     */
    private function filters(Request $request, array $countryCodes): array
    {
        $q = $request->string('q')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
        $type = $request->string('type')->trim()->toString();
        $countryCode = Str::upper($request->string('country_code')->trim()->toString());
        $from = $request->string('from')->trim()->toString();
        $to = $request->string('to')->trim()->toString();

        if (! Str::isUuid($q) && (! mb_check_encoding($q, 'UTF-8') || mb_strlen($q) < 2 || mb_strlen($q) > 80 || preg_match('/[%_\\\\]/', $q) === 1)) {
            $q = '';
        }

        $from = $this->isValidDate($from) ? $from : '';
        $to = $this->isValidDate($to) ? $to : '';

        if ($from !== '' && $to !== '' && $from > $to) {
            $from = '';
            $to = '';
        }

        return [
            'q' => $q !== '' ? $q : null,
            'status' => in_array($status, EventStatus::values(), true) ? $status : null,
            'type' => in_array($type, EventType::values(), true) ? $type : null,
            'country_code' => in_array($countryCode, $countryCodes, true) ? $countryCode : null,
            'from' => $from !== '' ? $from : null,
            'to' => $to !== '' ? $to : null,
        ];
    }

    /** @return list<array{code: string, name: string}> */
    private function countries(): array
    {
        $locations = require database_path('data/gazetteer.php');
        $countries = [];

        foreach ($locations as $location) {
            $countries[$location['country_code']] = $location['country'];
        }

        asort($countries);

        return array_map(
            fn (string $name, string $code): array => ['code' => $code, 'name' => $name],
            $countries,
            array_keys($countries),
        );
    }

    private function isValidDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
