<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\EventAttendance;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyEventsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $attendances = EventAttendance::query()
            ->select('event_attendances.*')
            ->join('events', 'events.id', '=', 'event_attendances.event_id')
            ->where('event_attendances.user_id', $request->user()->id)
            ->whereNull('event_attendances.cancelled_at')
            ->where('events.ends_at', '>', now('UTC'))
            ->with(['event' => fn ($query) => $query
                ->select([
                    'id',
                    'title',
                    'type',
                    'status',
                    'starts_at',
                    'ends_at',
                    'timezone',
                    'venue_name',
                    'formatted_address',
                    'locality',
                    'region',
                    'country',
                    'image_set_key',
                ])
                ->with([
                    'media',
                    'imageSet.images' => fn ($images) => $images
                        ->select(['id', 'image_set_key', 'role', 'path', 'width', 'height', 'alt'])
                        ->orderByRaw("CASE WHEN role = 'cover' THEN 0 ELSE 1 END"),
                ])])
            ->orderBy('events.starts_at')
            ->orderBy('events.id')
            ->paginate(12)
            ->through(function (EventAttendance $attendance): array {
                $event = $attendance->event;
                $cover = $event->imageSet?->images->first();
                $uploadedCover = $event->media->first();

                return [
                    'intent' => $attendance->intent->value,
                    'event' => [
                        'id' => $event->id,
                        'title' => $event->title,
                        'type' => $event->type->value,
                        'status' => $event->status->value,
                        'starts_at' => $event->starts_at->toISOString(),
                        'ends_at' => $event->ends_at->toISOString(),
                        'timezone' => $event->timezone,
                        'venue_name' => $event->venue_name,
                        'formatted_address' => $event->formatted_address,
                        'locality' => $event->locality,
                        'region' => $event->region,
                        'country' => $event->country,
                        'cover' => $uploadedCover !== null
                            ? [
                                'path' => $uploadedCover->cardUrl(),
                                'width' => $uploadedCover->card_width,
                                'height' => $uploadedCover->card_height,
                                'alt' => $uploadedCover->alt,
                            ]
                            : ($cover === null ? null : [
                                'path' => $cover->path,
                                'width' => $cover->width,
                                'height' => $cover->height,
                                'alt' => $cover->alt,
                            ]),
                    ],
                ];
            });

        return Inertia::render('Account/MyEvents', [
            'attendances' => $attendances,
        ]);
    }
}
