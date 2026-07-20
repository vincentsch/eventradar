<?php

use App\Models\Event;
use App\Services\Discovery\EventDiscoverySearchGateway;
use App\Services\Discovery\PublicEventFeed;
use App\Services\Events\EventImageCatalogueImporter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Meilisearch\Exceptions\CommunicationException;
use Tests\Support\FakeEventDiscoverySearchGateway;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(EventImageCatalogueImporter::class)->replace();
    Date::setTestNow(CarbonImmutable::parse('2026-07-20 12:00:00', 'UTC'));
});

afterEach(fn () => Date::setTestNow());

it('serves both card routes from the same public discovery contract', function (string $uri) {
    Event::factory()->published()->create([
        'starts_at' => '2026-08-01 18:00:00',
        'ends_at' => '2026-08-01 20:00:00',
        'starts_on_local' => '2026-08-01',
    ]);

    $this->get($uri)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/Discover')
            ->has('events.data', 1)
            ->has('query')
            ->has('discovery')
            ->has('filters.types', 8)
            ->has('filters.locations', 1)
            ->where('discovery.mode', 'feed'));
})->with(['home' => '/', 'visual one' => '/events-visual-1']);

it('returns an explicit unavailable search state for provider failures', function () {
    Event::factory()->published()->create([
        'starts_at' => '2026-08-01 18:00:00',
        'ends_at' => '2026-08-01 20:00:00',
        'starts_on_local' => '2026-08-01',
    ]);
    $gateway = new FakeEventDiscoverySearchGateway;
    $gateway->exception = new CommunicationException('offline');
    app()->instance(EventDiscoverySearchGateway::class, $gateway);

    $this->get('/?q=design')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('discovery.mode', 'search')
            ->where('discovery.status', 'unavailable')
            ->where('discovery.providerCount', 0)
            ->where('discovery.hydratedCount', 0)
            ->has('events.data', 0));
});

it('accepts its own next cursor through the public request boundary', function () {
    for ($ordinal = 1; $ordinal <= 19; $ordinal++) {
        $startsAt = Date::now('UTC')->addDays($ordinal);
        Event::factory()->published()->create([
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHours(2),
            'starts_on_local' => $startsAt->toDateString(),
        ]);
    }

    $cursor = app(PublicEventFeed::class)
        ->page(Date::now('UTC')->toImmutable(), null)
        ->nextCursor;

    expect($cursor)->not->toBeNull();

    $this->get('/?cursor='.urlencode($cursor))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('discovery.mode', 'feed')
            ->has('events.data', 1));
});

it('hydrates a successful search response through the public route', function () {
    $event = Event::factory()->published()->create([
        'starts_at' => '2026-08-01 18:00:00',
        'ends_at' => '2026-08-01 20:00:00',
        'starts_on_local' => '2026-08-01',
    ]);
    $gateway = new FakeEventDiscoverySearchGateway;
    $gateway->ids = [$event->id];
    $gateway->total = 1;
    app()->instance(EventDiscoverySearchGateway::class, $gateway);

    $this->get('/?q=design')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('discovery.mode', 'search')
            ->where('discovery.status', 'ready')
            ->where('discovery.providerCount', 1)
            ->where('discovery.hydratedCount', 1)
            ->has('events.data', 1)
            ->where('events.data.0.id', $event->id));
});
