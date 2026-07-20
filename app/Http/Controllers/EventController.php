<?php

namespace App\Http\Controllers;

use App\Domain\Events\EventStatus;
use App\Models\Event;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
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
}
