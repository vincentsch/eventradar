<?php

use App\Models\Event;
use App\Services\Discovery\EventDiscoverySearchGateway;
use App\Services\Events\EventImageCatalogueImporter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\Date;
use Tests\Support\FakeEventDiscoverySearchGateway;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(EventImageCatalogueImporter::class)->replace();
    Date::setTestNow(CarbonImmutable::parse('2026-07-20 12:00:00', 'UTC'));
    app()->instance(
        EventDiscoverySearchGateway::class,
        new FakeEventDiscoverySearchGateway,
    );
});

afterEach(fn () => Date::setTestNow());

it('normalizes discovery input and sends canonical values to the provider', function () {
    $location = Event::factory()->published()->create([
        'location_key' => 'de-berlin',
        'starts_at' => '2026-08-01 18:00:00',
        'ends_at' => '2026-08-01 20:00:00',
        'starts_on_local' => '2026-08-01',
    ]);
    $gateway = app(EventDiscoverySearchGateway::class);

    $this->get('/?q=%20design%20&type=exhibition&location='.$location->location_key.'&from=2026-08-01&to=2026-08-31')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/Discover')
            ->where('query.q', 'design')
            ->where('query.type', 'exhibition')
            ->where('query.location', 'de-berlin')
            ->where('query.from', '2026-08-01')
            ->where('query.to', '2026-08-31')
            ->where('discovery.mode', 'search')
            ->where('discovery.status', 'ready'));

    expect($gateway->requests)->toHaveCount(1)
        ->and($gateway->requests[0]['query'])->toBe('design');
});

it('rejects invalid enums locations dates and continuation combinations', function (string $query, string $field) {
    $this->get('/?'.$query)
        ->assertSessionHasErrors($field);
})->with([
    'unknown type' => ['type=party', 'type'],
    'unknown location' => ['location=moon-base', 'location'],
    'malformed paired dates' => ['from=x&to=y', 'from'],
    'backwards dates' => ['from=2026-08-02&to=2026-08-01', 'to'],
    'range over ninety three days' => ['from=2026-01-01&to=2026-04-05', 'to'],
    'invalid cursor' => ['cursor=not-a-cursor', 'cursor'],
    'cursor during discovery' => ['q=design&cursor=eyJpZCI6MX0', 'cursor'],
    'page without discovery' => ['page=2', 'page'],
]);

it('treats whitespace-only discovery fields as the unfiltered feed', function () {
    $this->get('/?q=%20%20%20')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('query.q', null)
            ->where('discovery.mode', 'feed'));
});

it('rejects a valid feed cursor when discovery is active', function () {
    $cursor = (new Cursor([
        'starts_at' => '2026-08-01 18:00:00',
        'id' => '01981f4c-3c00-7000-8000-000000000001',
    ]))->encode();

    $this->get('/?q=design&cursor='.urlencode($cursor))
        ->assertSessionHasErrors('cursor');
});

it('rejects decoded cursors with missing or non-scalar ordered parameters', function (string $cursor) {
    $this->get('/?cursor='.urlencode($cursor))
        ->assertSessionHasErrors('cursor');
})->with([
    'missing ordered parameters' => fn () => (new Cursor([]))->encode(),
    'array start parameter' => fn () => (new Cursor([
        'starts_at' => ['2026-08-01 18:00:00'],
        'id' => '01981f4c-3c00-7000-8000-000000000001',
    ]))->encode(),
    'invalid timestamp' => fn () => (new Cursor([
        'starts_at' => '2026-02-30 18:00:00',
        'id' => '01981f4c-3c00-7000-8000-000000000001',
    ]))->encode(),
    'non uuid identifier' => fn () => (new Cursor([
        'starts_at' => '2026-08-01 18:00:00',
        'id' => 'not-a-uuid',
    ]))->encode(),
    'extra parameter' => fn () => (new Cursor([
        'starts_at' => '2026-08-01 18:00:00',
        'id' => '01981f4c-3c00-7000-8000-000000000001',
        'extra' => 'value',
    ]))->encode(),
    'non boolean direction' => fn () => (new Cursor([
        'starts_at' => '2026-08-01 18:00:00',
        'id' => '01981f4c-3c00-7000-8000-000000000001',
    ], 'next'))->encode(),
]);

it('rejects malformed utf eight search text', function () {
    $this->call('GET', '/', ['q' => "\xC3\x28"])
        ->assertSessionHasErrors('q');
});
