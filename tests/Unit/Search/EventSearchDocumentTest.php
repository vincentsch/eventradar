<?php

use App\Models\Event;
use App\Services\Search\EventSearchDocument;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

it('builds a thin public document with numeric price and no private provenance', function () {
    $event = new Event;
    $event->setRawAttributes([
        'id' => '01981f4c-3c00-7000-8000-000000000001',
        'title' => 'Berlin Design Night',
        'organizer_name' => 'Design Collective',
        'venue_name' => 'Central Hall',
        'starts_at' => '2026-08-01 18:00:00',
        'ends_at' => '2026-08-01 21:00:00',
        'starts_on_local' => '2026-08-01',
        'location_key' => 'de-berlin',
        'locality' => 'Berlin',
        'region' => null,
        'country' => 'Germany',
        'country_code' => 'DE',
        'latitude' => '52.5200000',
        'longitude' => '13.4050000',
        'status' => 'published',
        'type' => 'exhibition',
        'tags' => json_encode(['design', 'featured'], JSON_THROW_ON_ERROR),
        'minimum_price' => '19.90',
        'payload' => '{"private":"source"}',
        'user_id' => 42,
    ], true);

    $document = (new EventSearchDocument)->build($event);

    expect($document)
        ->toMatchArray([
            'id' => '01981f4c-3c00-7000-8000-000000000001',
            'status' => 'published',
            'type' => 'exhibition',
            'starts_on_local' => '2026-08-01',
            'starts_at_timestamp' => CarbonImmutable::parse('2026-08-01 18:00:00', 'UTC')->getTimestamp(),
            'minimum_price' => 19.9,
            '_geo' => ['lat' => 52.52, 'lng' => 13.405],
        ])
        ->and($document['minimum_price'])->toBeFloat()
        ->and($document)->not->toHaveKeys(['payload', 'user_id', 'description', 'capacity', 'currency_code']);
});

it('omits geo for events without coordinates and keeps free events numeric', function () {
    $event = new Event;
    $event->setRawAttributes([
        'id' => '01981f4c-3c00-7000-8000-000000000002',
        'title' => 'Remote Meetup',
        'organizer_name' => 'Community',
        'venue_name' => 'Online',
        'starts_at' => '2026-08-02 18:00:00',
        'ends_at' => '2026-08-02 20:00:00',
        'starts_on_local' => '2026-08-02',
        'location_key' => 'online',
        'locality' => 'Online',
        'region' => null,
        'country' => 'Germany',
        'country_code' => 'DE',
        'latitude' => null,
        'longitude' => null,
        'status' => 'sold_out',
        'type' => 'meetup',
        'tags' => '[]',
        'minimum_price' => '0.00',
    ], true);

    $document = (new EventSearchDocument)->build($event);

    expect($document)->not->toHaveKey('_geo')
        ->and($document['minimum_price'])->toBe(0.0);
});

it('keeps search settings deliberately small with a one thousand hit ceiling', function () {
    config()->set('meilisearch.pagination_max_total_hits', 1_000);

    $settings = (new EventSearchDocument)->settings();

    expect($settings['searchableAttributes'])->toBe(EventSearchDocument::SEARCHABLE_ATTRIBUTES)
        ->and($settings['filterableAttributes'])->toBe(EventSearchDocument::FILTERABLE_ATTRIBUTES)
        ->and($settings['sortableAttributes'])->toBe(['starts_at_timestamp', 'minimum_price'])
        ->and($settings['pagination']['maxTotalHits'])->toBe(1_000);
});
