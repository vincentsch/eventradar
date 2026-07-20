<?php

use App\Models\Event;
use App\Services\Events\EventImageCatalogueImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(EventImageCatalogueImporter::class)->replace();
    config()->set('services.mapbox.geocoding_token', 'secret-server-token');
});

it('reverse geocodes one viewed public event and caches the permanent result', function () {
    Http::fake([
        'api.mapbox.com/*' => Http::response([
            'features' => [[
                'geometry' => ['coordinates' => [13.4132, 52.5219]],
                'properties' => [
                    'name' => 'Alexanderplatz 1',
                    'full_address' => 'Alexanderplatz 1, 10178 Berlin, Germany',
                    'context' => [
                        'postcode' => ['name' => '10178'],
                        'place' => ['name' => 'Berlin'],
                        'region' => ['name' => 'Berlin'],
                        'country' => ['name' => 'Germany', 'country_code' => 'de'],
                    ],
                ],
            ]],
        ]),
    ]);
    $event = Event::factory()->published()->create([
        'starts_at' => now('UTC')->addDays(5),
        'ends_at' => now('UTC')->addDays(5)->addHours(2),
        'starts_on_local' => now('Europe/Berlin')->addDays(5)->toDateString(),
        'formatted_address' => null,
        'latitude' => 52.5219,
        'longitude' => 13.4132,
    ]);

    $this->getJson("/events/{$event->id}/address")
        ->assertOk()
        ->assertJsonPath('address', 'Alexanderplatz 1, 10178 Berlin, Germany');
    $this->getJson("/events/{$event->id}/address")->assertOk();

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['permanent'] === 'true'
        && $request['latitude'] === 52.5219
        && $request['longitude'] === 13.4132);
    $this->assertDatabaseHas('events', [
        'id' => $event->id,
        'formatted_address' => 'Alexanderplatz 1, 10178 Berlin, Germany',
    ]);
});

it('does not expose address resolution for unpublished events', function () {
    $event = Event::factory()->create(['status' => 'draft']);

    $this->getJson("/events/{$event->id}/address")->assertNotFound();
    Http::assertNothingSent();
});
