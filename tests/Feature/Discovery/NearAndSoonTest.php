<?php

use App\Models\Event;
use App\Services\Discovery\EventDiscoverySearchGateway;
use App\Services\Discovery\EventDiscoverySearchResult;
use App\Services\Events\EventImageCatalogueImporter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Meilisearch\Exceptions\CommunicationException;
use Tests\Support\FakeEventDiscoverySearchGateway;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(EventImageCatalogueImporter::class)->replace();
    Date::setTestNow(CarbonImmutable::parse('2026-07-20 12:00:00', 'UTC'));
});

afterEach(fn () => Date::setTestNow());

it('hydrates bounded map results in provider order with anti-meridian viewport filters', function () {
    $longDescription = str_repeat('A detailed event description. ', 40);
    $first = Event::factory()->published()->create([
        'title' => 'First map event',
        'description' => $longDescription,
        'starts_at' => now('UTC')->addDays(2),
        'ends_at' => now('UTC')->addDays(2)->addHours(2),
        'starts_on_local' => now('UTC')->addDays(2)->toDateString(),
    ]);
    $second = Event::factory()->published()->create([
        'title' => 'Second map event',
        'starts_at' => now('UTC')->addDay(),
        'ends_at' => now('UTC')->addDay()->addHours(2),
        'starts_on_local' => now('UTC')->addDay()->toDateString(),
    ]);
    $gateway = new class([$second->id, $first->id]) implements EventDiscoverySearchGateway
    {
        /** @var list<string|list<string>> */
        public array $filters = [];

        /** @param list<string> $ids */
        public function __construct(private readonly array $ids) {}

        public function searchIds(string $query, array $filters, array $sort, int $page, int $perPage): EventDiscoverySearchResult
        {
            $this->filters = $filters;

            return new EventDiscoverySearchResult($this->ids, count($this->ids), 1, 3);
        }
    };
    app()->instance(EventDiscoverySearchGateway::class, $gateway);

    $this->get('/events-visual-2?'.http_build_query([
        'north' => 60,
        'south' => -40,
        'west' => 170,
        'east' => -170,
        'type' => 'meetup',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/NearAndSoon')
            ->has('events', 2)
            ->where('events.0.id', $second->id)
            ->where('events.1.id', $first->id)
            ->where('events.1.description', Str::limit($longDescription, 480, '...'))
            ->where('discovery.providerCount', 2)
            ->where('discovery.hydratedCount', 2)
            ->where('discovery.limit', 200)
            ->where('query.west', 170)
            ->where('query.east', -170));

    $viewportFilter = collect($gateway->filters)->first(fn ($filter) => is_array($filter));
    expect($viewportFilter)->toHaveCount(2)
        ->and($viewportFilter[0])->toBe('_geoBoundingBox([60.0000000, 180.0000000], [-40.0000000, 170.0000000])')
        ->and($viewportFilter[1])->toBe('_geoBoundingBox([60.0000000, -170.0000000], [-40.0000000, -180.0000000])');
});

it('rejects incomplete or inverted map bounds', function () {
    $this->get('/events-visual-2?north=10&south=20')
        ->assertSessionHasErrors(['north', 'east', 'west']);
});

it('returns an explicit unavailable state when map discovery fails', function () {
    $gateway = new FakeEventDiscoverySearchGateway;
    $gateway->exception = new CommunicationException('offline');
    app()->instance(EventDiscoverySearchGateway::class, $gateway);

    $this->get('/events-visual-2')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/NearAndSoon')
            ->has('events', 0)
            ->where('discovery.status', 'unavailable')
            ->where('discovery.providerCount', 0)
            ->where('discovery.hydratedCount', 0)
            ->where('discovery.limit', 200));
});
