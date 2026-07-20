<?php

use App\Models\Event;
use App\Services\Discovery\PublicEventData;
use App\Services\Events\EventImageCatalogueImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => app(EventImageCatalogueImporter::class)->replace());

it('maps canonical event time location and both checked images without private fields', function () {
    $event = Event::factory()->published()->create([
        'starts_at' => '2026-08-21 19:30:00',
        'ends_at' => '2026-08-21 21:30:00',
        'timezone' => 'Europe/Berlin',
        'starts_on_local' => '2026-08-21',
        'locality' => 'Berlin',
        'region' => null,
        'country' => 'Germany',
        'image_set_key' => 'concert-industrial-after-dark',
        'type' => 'concert',
        'payload' => '{"private":"source"}',
    ])->load('imageSet.images');

    $data = (new PublicEventData)->build($event);

    expect($data)->toMatchArray([
        'id' => $event->id,
        'status' => 'published',
        'href' => "/events/{$event->id}",
        'category' => 'concert',
        'startsAt' => '2026-08-21T19:30:00Z',
        'localDate' => '2026-08-21',
        'dateLabel' => '21 Aug',
        'timeLabel' => '21:30',
        'timezoneLabel' => 'CEST',
        'timezone' => 'Europe/Berlin',
        'locationLabel' => 'Berlin, Germany',
        'image' => [
            'src' => '/images/events/concert-industrial-after-dark/cover.webp',
            'alt' => 'Electronic performer playing to a crowd inside a converted brick hall',
        ],
        'detailImage' => [
            'src' => '/images/events/concert-industrial-after-dark/detail.webp',
            'alt' => 'Crowd and stage lighting across a converted industrial concert venue',
        ],
    ])->and($data)->not->toHaveKeys(['payload', 'user_id', 'owner', 'organizer_email']);
});

it('uses the event timezone through DST and preserves coordinate-less cards', function () {
    $event = Event::factory()->published()->withoutCoordinates()->create([
        'starts_at' => '2026-12-21 19:30:00',
        'ends_at' => '2026-12-21 21:30:00',
        'timezone' => 'Europe/Berlin',
        'starts_on_local' => '2026-12-21',
    ])->load('imageSet.images');

    $data = (new PublicEventData)->build($event);

    expect($data['timeLabel'])->toBe('20:30')
        ->and($data['timezoneLabel'])->toBe('CET')
        ->and($data['latitude'])->toBeNull()
        ->and($data['longitude'])->toBeNull();
});
