<?php

use App\Models\Event;
use App\Services\Discovery\PublicEventData;
use App\Services\Discovery\PublicEventQuery;
use App\Services\Discovery\PublicEventSearch;
use App\Services\Discovery\PublicEventVisibility;
use App\Services\Events\EventImageCatalogueImporter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeEventDiscoverySearchGateway;

uses(RefreshDatabase::class);

beforeEach(fn () => app(EventImageCatalogueImporter::class)->replace());

it('builds safe visibility filters and hydrates canonical rows in provider order', function () {
    $instant = CarbonImmutable::parse('2026-07-20 12:00:00', 'UTC');
    $first = Event::factory()->published()->create([
        'starts_at' => '2026-08-02 18:00:00',
        'ends_at' => '2026-08-02 20:00:00',
        'starts_on_local' => '2026-08-02',
    ]);
    $second = Event::factory()->soldOut()->create([
        'starts_at' => '2026-08-01 18:00:00',
        'ends_at' => '2026-08-01 20:00:00',
        'starts_on_local' => '2026-08-01',
    ]);
    $private = Event::factory()->create(['status' => 'draft']);
    $gateway = new FakeEventDiscoverySearchGateway;
    $gateway->ids = [$first->id, $private->id, $second->id, 'missing-id'];
    $gateway->total = 47;
    $service = new PublicEventSearch($gateway, new PublicEventVisibility, new PublicEventData);
    $query = new PublicEventQuery(
        search: 'design',
        types: ['exhibition', 'workshop'],
        locations: ['de-berlin', 'fr-paris'],
        from: '2026-08-01',
        to: '2026-08-31',
        includeOngoing: false,
        cursor: null,
        page: 2,
    );

    $result = $service->page($query, $instant, '/events-visual-1');
    $request = $gateway->requests[0];

    expect($request)->toMatchArray([
        'query' => 'design',
        'sort' => ['starts_at_timestamp:asc', 'id:asc'],
        'page' => 2,
        'per_page' => 18,
    ])->and($request['filters'])->toContain(
        'status IN ["published","sold_out"]',
        'ends_at_timestamp > '.$instant->getTimestamp(),
        'starts_at_timestamp >= '.$instant->getTimestamp(),
        'type IN ["exhibition","workshop"]',
        'location_key IN ["de-berlin","fr-paris"]',
        'starts_on_local_number >= 20260801',
        'starts_on_local_number <= 20260831',
    )->and(collect($result['paginator']->items())->pluck('id')->all())->toBe([$first->id, $second->id])
        ->and($result['provider_count'])->toBe(47)
        ->and($result['hydrated_count'])->toBe(2)
        ->and($result['paginator']->total())->toBe(47);
});

it('quotes exact filter values containing quotes and backslashes', function () {
    $instant = CarbonImmutable::parse('2026-07-20 12:00:00', 'UTC');
    $gateway = new FakeEventDiscoverySearchGateway;
    $service = new PublicEventSearch($gateway, new PublicEventVisibility, new PublicEventData);
    $query = new PublicEventQuery(
        search: null,
        types: [],
        locations: ['de-"quoted\\place'],
        from: null,
        to: null,
        includeOngoing: true,
        cursor: null,
        page: 1,
    );

    $service->page($query, $instant, '/');

    expect($gateway->requests[0]['filters'])
        ->toContain('location_key IN ["de-\\"quoted\\\\place"]')
        ->not->toContain('starts_at_timestamp >= '.$instant->getTimestamp());
});
