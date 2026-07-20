<?php

namespace App\Http\Controllers;

use App\Domain\Events\EventStatus;
use App\Models\Event;
use App\Models\EventAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function show(Request $request, string $event): Response
    {
        $record = Event::query()
            ->select([
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
            ])
            ->with([
                'media',
                'imageSet.images' => fn ($query) => $query
                    ->select(['id', 'image_set_key', 'role', 'path', 'width', 'height', 'alt'])
                    ->orderBy('role'),
            ])
            ->whereKey($event)
            ->whereIn('status', EventStatus::publicValues())
            ->where('ends_at', '>', Date::now('UTC'))
            ->firstOrFail();

        $attendanceCounts = EventAttendance::query()
            ->selectRaw('intent, COUNT(*) AS aggregate')
            ->where('event_id', $record->id)
            ->whereNull('cancelled_at')
            ->groupBy('intent')
            ->pluck('aggregate', 'intent')
            ->map(fn (int|string $count): int => (int) $count);

        $publicAttendees = DB::table('event_attendances')
            ->join('users', 'users.id', '=', 'event_attendances.user_id')
            ->where('event_attendances.event_id', $record->id)
            ->whereNull('event_attendances.cancelled_at')
            ->orderBy('event_attendances.created_at')
            ->limit(12)
            ->get(['users.name', 'event_attendances.intent'])
            ->map(fn (object $attendance): array => [
                'name' => $this->safeDisplayName((string) $attendance->name),
                'intent' => (string) $attendance->intent,
            ])
            ->all();

        $viewerAttendance = $request->user() === null
            ? null
            : EventAttendance::query()
                ->where('event_id', $record->id)
                ->where('user_id', $request->user()->id)
                ->whereNull('cancelled_at')
                ->first(['intent']);

        return Inertia::render('Events/Show', [
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
                ]),
                'images' => $record->media->isNotEmpty()
                    ? $record->media->map(fn ($image): array => [
                        'role' => $image->position === 0 ? 'cover' : 'gallery',
                        'path' => $image->url(),
                        'width' => $image->width,
                        'height' => $image->height,
                        'alt' => $image->alt,
                    ])->values()->all()
                    : $record->imageSet?->images->map(fn ($image): array => $image->only([
                        'role', 'path', 'width', 'height', 'alt',
                    ]))->values()->all() ?? [],
            ],
            'attendance' => [
                'viewer_intent' => $viewerAttendance?->intent->value,
                'counts' => [
                    'going' => $attendanceCounts->get('going', 0),
                    'interested' => $attendanceCounts->get('interested', 0),
                    'total' => $attendanceCounts->sum(),
                ],
                'attendees' => $publicAttendees,
            ],
        ]);
    }

    private function safeDisplayName(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($parts === []) {
            return 'Guest';
        }

        if (count($parts) === 1) {
            return $parts[0];
        }

        return $parts[0].' '.mb_strtoupper(mb_substr($parts[array_key_last($parts)], 0, 1)).'.';
    }
}
