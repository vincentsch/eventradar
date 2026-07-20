<?php

use App\Models\Event;
use App\Models\User;
use App\Services\Events\EventImageCatalogueImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(fn () => app(EventImageCatalogueImporter::class)->replace());
afterEach(fn () => Date::setTestNow());

it('shows visible normalized event detail without raw provenance or owner identity', function () {
    Date::setTestNow('2026-08-20 12:00:00');
    $user = User::factory()->create(['email' => 'private-owner@example.test']);
    $event = Event::factory()->for($user)->published()->ongoing()->create([
        'title' => 'Global Tech Summit',
        'description' => 'A complete normalized event description.',
        'payload' => '{"secret_source_key":"must-not-leak"}',
    ]);

    $this->get(route('events.show', $event))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Events/Show')
            ->where('event.id', $event->id)
            ->where('event.title', 'Global Tech Summit')
            ->where('event.description', 'A complete normalized event description.')
            ->has('event.images', 2)
            ->missing('event.payload')
            ->missing('event.user_id')
            ->missing('event.user')
            ->missing('event.owner')
        );
});

it('shows a non-ended sold-out event detail', function () {
    Date::setTestNow('2026-08-20 12:00:00');
    $event = Event::factory()->soldOut()->create([
        'starts_at' => '2026-08-21 18:00:00',
        'ends_at' => '2026-08-21 20:00:00',
        'starts_on_local' => '2026-08-21',
    ]);

    $this->get(route('events.show', $event))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('event.status', 'sold_out'));
});

it('uses the same not-found response for hidden, ended, and unknown events', function () {
    Date::setTestNow('2026-08-20 12:00:00');
    $draft = Event::factory()->ongoing()->create(['status' => 'draft']);
    $cancelled = Event::factory()->ongoing()->create(['status' => 'cancelled']);
    $ended = Event::factory()->published()->create([
        'starts_at' => '2026-08-19 10:00:00',
        'ends_at' => '2026-08-19 12:00:00',
        'starts_on_local' => '2026-08-19',
    ]);

    $this->get(route('events.show', $draft))->assertNotFound();
    $this->get(route('events.show', $cancelled))->assertNotFound();
    $this->get(route('events.show', $ended))->assertNotFound();
    $this->get(route('events.show', '00000000-0000-7000-8000-000000000000'))->assertNotFound();
});

it('returns a stored event address without calling Mapbox', function () {
    Date::setTestNow('2026-08-20 12:00:00');
    Http::fake();
    $event = Event::factory()->published()->ongoing()->create([
        'formatted_address' => 'Alexanderplatz 1, 10178 Berlin, Germany',
        'address_line_1' => 'Alexanderplatz 1',
        'latitude' => 52.5219,
        'longitude' => 13.4132,
    ]);

    $this->getJson(route('events.location', $event))
        ->assertOk()
        ->assertJsonPath('address', 'Alexanderplatz 1, 10178 Berlin, Germany')
        ->assertJsonPath('resolution', 'stored')
        ->assertJsonPath('approximate', false);

    Http::assertNothingSent();
});

it('reverse geocodes a location only when requested and caches the result', function () {
    Date::setTestNow('2026-08-20 12:00:00');
    config()->set('services.mapbox.geocoding_token', 'secret-server-token');
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
    $event = Event::factory()->published()->ongoing()->create([
        'formatted_address' => null,
        'address_line_1' => null,
        'latitude' => 52.5219,
        'longitude' => 13.4132,
    ]);

    $this->getJson(route('events.location', $event))
        ->assertOk()
        ->assertJsonPath('address', 'Alexanderplatz 1, 10178 Berlin, Germany')
        ->assertJsonPath('resolution', 'reverse')
        ->assertJsonPath('approximate', true);
    $this->getJson(route('events.location', $event))->assertOk();

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/reverse')
        && $request['permanent'] === 'true'
        && $request['access_token'] === 'secret-server-token');
});
