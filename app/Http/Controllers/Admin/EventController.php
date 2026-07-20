<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Events\EventStatus;
use App\Domain\Events\EventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveEventRequest;
use App\Jobs\ReconcileEventSearchIndex;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\EventImage;
use App\Models\EventMedia;
use App\Services\Attendance\AttendanceManager;
use App\Services\Discovery\PublicEventFilterOptions;
use App\Services\Events\EventLocalDateTimeResolver;
use App\Services\Events\EventMediaManager;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

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

    /** @var list<string> */
    private const EDIT_COLUMNS = [
        'id', 'title', 'description', 'organizer_name', 'venue_name',
        'formatted_address', 'address_line_1', 'postal_code', 'locality',
        'region', 'country', 'country_code', 'latitude', 'longitude',
        'timezone', 'starts_at', 'ends_at', 'starts_on_local', 'location_key',
        'image_set_key', 'status', 'type', 'tags', 'minimum_price',
        'currency_code', 'capacity', 'created_at', 'updated_at',
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
            // With a local-date filter, leading the sort with the filtered
            // column lets MySQL serve the range and the order from
            // events_admin_local_date_index instead of scanning the whole
            // catalogue backwards along starts_at.
            ->when(
                $filters['from'] !== null || $filters['to'] !== null,
                fn (Builder $query) => $query->orderByDesc('starts_on_local'),
            )
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

    public function create(): Response
    {
        return Inertia::render('Admin/Events/Form', [
            'event' => null,
            'options' => $this->formOptions(),
        ]);
    }

    public function store(
        SaveEventRequest $request,
        EventLocalDateTimeResolver $dates,
        EventMediaManager $media,
        PublicEventFilterOptions $filters,
    ): RedirectResponse {
        $attributes = $this->attributes($request, $dates);
        $event = new Event($attributes);
        $event->id = $event->newUniqueId();
        $event->user_id = $request->user()->id;
        $event->image_set_key = $this->defaultImageSet($attributes['type']);
        $event->setAttribute('payload', json_encode(['source' => 'admin'], JSON_THROW_ON_ERROR));
        $prepared = $media->prepare($event, $this->uploadedImages($request));

        try {
            DB::transaction(function () use ($event, $media, $prepared, $filters): void {
                $event->save();
                $media->replace($event, $prepared);
                DB::afterCommit(fn () => $filters->forget());
                ReconcileEventSearchIndex::dispatch($event->id)->onQueue('search')->afterCommit();
            });
        } catch (Throwable $exception) {
            $media->discard($prepared);

            throw $exception;
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Event created.']);

        return to_route('admin.events.show', $event);
    }

    public function edit(string $event): Response
    {
        $record = Event::query()
            ->select(self::EDIT_COLUMNS)
            ->with(['media', 'imageSet.images'])
            ->findOrFail($event);
        $start = CarbonImmutable::instance($record->starts_at)->setTimezone($record->timezone);
        $end = CarbonImmutable::instance($record->ends_at)->setTimezone($record->timezone);

        return Inertia::render('Admin/Events/Form', [
            'event' => [
                ...$record->only([
                    'id', 'title', 'description', 'organizer_name', 'venue_name',
                    'formatted_address', 'address_line_1', 'postal_code', 'locality',
                    'region', 'country', 'country_code', 'latitude', 'longitude',
                    'timezone', 'status', 'type', 'tags', 'minimum_price',
                    'currency_code', 'capacity',
                ]),
                'starts_at_local' => $start->format('Y-m-d\TH:i'),
                'ends_at_local' => $end->format('Y-m-d\TH:i'),
                'starts_at_offset' => $start->format('P'),
                'ends_at_offset' => $end->format('P'),
                'images' => $this->images($record),
            ],
            'options' => $this->formOptions(),
        ]);
    }

    public function update(
        SaveEventRequest $request,
        string $event,
        EventLocalDateTimeResolver $dates,
        EventMediaManager $media,
        AttendanceManager $attendance,
        PublicEventFilterOptions $filters,
    ): RedirectResponse {
        $record = Event::query()->select(self::EDIT_COLUMNS)->findOrFail($event);
        $originalStart = $record->starts_at->getTimestamp();
        $originalStatus = $record->status;
        $record->fill($this->attributes($request, $dates));
        $scheduleChanged = $record->starts_at->getTimestamp() !== $originalStart;
        $visibilityChanged = $record->status !== $originalStatus;
        $prepared = $request->hasFile('images')
            ? $media->prepare($record, $this->uploadedImages($request))
            : null;

        try {
            DB::transaction(function () use (
                $record,
                $media,
                $prepared,
                $scheduleChanged,
                $visibilityChanged,
                $attendance,
                $filters,
            ): void {
                $record->save();

                if ($prepared !== null) {
                    $media->replace($record, $prepared);
                }

                if ($scheduleChanged || $visibilityChanged) {
                    $attendance->rescheduleForEvent($record);
                }

                DB::afterCommit(fn () => $filters->forget());
                ReconcileEventSearchIndex::dispatch($record->id)->onQueue('search')->afterCommit();
            });
        } catch (Throwable $exception) {
            if ($prepared !== null) {
                $media->discard($prepared);
            }

            throw $exception;
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Event updated.']);

        return to_route('admin.events.show', $record);
    }

    public function destroy(string $event, PublicEventFilterOptions $filters): RedirectResponse
    {
        $record = Event::query()
            ->select(['id', 'status'])
            ->withCount('attendances')
            ->findOrFail($event);

        if ($record->status !== EventStatus::Draft || $record->attendances_count > 0) {
            throw ValidationException::withMessages([
                'event' => 'Only draft events without attendance history can be permanently deleted.',
            ]);
        }

        DB::transaction(function () use ($record, $filters): void {
            $paths = $record->media()
                ->get(['path', 'card_path'])
                ->flatMap(fn ($media): array => [$media->path, $media->card_path])
                ->all();
            $id = $record->id;
            $record->delete();
            DB::afterCommit(fn () => Storage::disk('public')->delete($paths));
            DB::afterCommit(fn () => $filters->forget());
            ReconcileEventSearchIndex::dispatch($id)->onQueue('search')->afterCommit();
        }, 3);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Draft event deleted.']);

        return to_route('admin.events.index');
    }

    public function show(string $event): Response
    {
        $query = Event::query()->select([
            'id',
            'title',
            'description',
            'organizer_name',
            'venue_name',
            'formatted_address',
            'address_line_1',
            'starts_at',
            'ends_at',
            'timezone',
            'starts_on_local',
            'location_key',
            'locality',
            'region',
            'postal_code',
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
            ->with([
                'media',
                'imageSet.images' => fn ($query) => $query
                    ->select(['id', 'image_set_key', 'role', 'path', 'width', 'height', 'alt'])
                    ->orderBy('role'),
            ])
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
                    'formatted_address',
                    'address_line_1',
                    'starts_at',
                    'ends_at',
                    'timezone',
                    'starts_on_local',
                    'location_key',
                    'locality',
                    'region',
                    'postal_code',
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
                'images' => $this->images($record),
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

    /** @return array<string, mixed> */
    private function attributes(SaveEventRequest $request, EventLocalDateTimeResolver $dates): array
    {
        $validated = $request->validated();
        $start = $dates->resolve(
            $validated['starts_at_local'],
            $validated['timezone'],
            $validated['starts_at_offset'] ?? null,
            'starts_at_local',
        );
        $end = $dates->resolve(
            $validated['ends_at_local'],
            $validated['timezone'],
            $validated['ends_at_offset'] ?? null,
            'ends_at_local',
        );

        if ($end->lte($start)) {
            throw ValidationException::withMessages(['ends_at_local' => 'The event must end after it starts.']);
        }

        return [
            'title' => $validated['title'],
            'description' => $validated['description'],
            'organizer_name' => $validated['organizer_name'],
            'venue_name' => $validated['venue_name'],
            'formatted_address' => $validated['formatted_address'],
            'address_line_1' => $validated['address_line_1'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'locality' => $validated['locality'],
            'region' => $validated['region'] ?? null,
            'country' => $validated['country'],
            'country_code' => $validated['country_code'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'timezone' => $validated['timezone'],
            'starts_at' => $start,
            'ends_at' => $end,
            'starts_on_local' => $start->setTimezone($validated['timezone'])->toDateString(),
            'location_key' => Str::limit(Str::lower($validated['country_code']).'-'.Str::slug($validated['locality']), 64, ''),
            'status' => $validated['status'],
            'type' => $validated['type'],
            'tags' => array_values(array_unique(array_filter(array_map(
                fn (string $tag): string => trim($tag),
                $validated['tags'],
            )))),
            'minimum_price' => $validated['minimum_price'] ?? null,
            'currency_code' => $validated['currency_code'] ?? null,
            'capacity' => $validated['capacity'] ?? null,
        ];
    }

    /** @return array{statuses: list<string>, types: list<string>, timezones: list<string>} */
    private function formOptions(): array
    {
        return [
            'statuses' => EventStatus::values(),
            'types' => EventType::values(),
            'timezones' => timezone_identifiers_list(),
        ];
    }

    /** @return list<array<string, int|string>> */
    private function images(Event $event): array
    {
        if ($event->media->isNotEmpty()) {
            return array_values($event->media->map(fn (EventMedia $image): array => [
                'role' => $image->position === 0 ? 'cover' : 'gallery',
                'path' => $image->url(),
                'width' => $image->width,
                'height' => $image->height,
                'alt' => $image->alt,
            ])->all());
        }

        if ($event->imageSet === null) {
            return [];
        }

        return array_values($event->imageSet->images->map(fn (EventImage $image): array => [
            'role' => $image->role->value,
            'path' => $image->path,
            'width' => $image->width,
            'height' => $image->height,
            'alt' => $image->alt,
        ])->all());
    }

    private function defaultImageSet(string $type): string
    {
        return match ($type) {
            'concert' => 'concert-industrial-after-dark',
            'conference' => 'conference-timber-ideas-forum',
            'meetup' => 'meetup-neighborhood-makers-table',
            'workshop' => 'workshop-ceramic-studio',
            'festival' => 'festival-garden-long-table',
            'sports' => 'sports-community-track-evening',
            'networking' => 'networking-architecture-studio-social',
            'exhibition' => 'exhibition-adaptive-reuse-opening',
            default => throw new \LogicException("Unsupported event type [{$type}]."),
        };
    }

    /** @return list<UploadedFile> */
    private function uploadedImages(SaveEventRequest $request): array
    {
        $files = $request->file('images');

        if ($files instanceof UploadedFile) {
            return [$files];
        }

        if (! is_array($files)) {
            return [];
        }

        return array_values($files);
    }
}
