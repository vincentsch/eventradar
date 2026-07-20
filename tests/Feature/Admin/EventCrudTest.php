<?php

use App\Jobs\ReconcileEventSearchIndex;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\User;
use App\Services\Events\EventImageCatalogueImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(EventImageCatalogueImporter::class)->replace();
    $this->actingAs(User::factory()->admin()->create());
});

it('creates an event from local time with an optimized local gallery', function () {
    Storage::fake('public');
    Queue::fake();

    $this->post('/admin/events', validEventInput([
        'images' => [
            UploadedFile::fake()->image('cover.jpg', 1600, 1000),
            UploadedFile::fake()->image('detail.png', 1200, 900),
        ],
    ]))->assertRedirect();

    $event = Event::query()->where('title', 'Admin-created summer meetup')->firstOrFail();

    expect($event->starts_at->utc()->format('Y-m-d H:i:s'))->toBe('2026-08-14 16:00:00')
        ->and($event->starts_on_local->format('Y-m-d'))->toBe('2026-08-14')
        ->and($event->formatted_address)->toBe('Alexanderplatz 1, 10178 Berlin, Germany')
        ->and($event->media)->toHaveCount(2)
        ->and($event->media->first()->position)->toBe(0)
        ->and($event->media->first()->mime_type)->toBe('image/webp');

    foreach ($event->media as $media) {
        Storage::disk('public')->assertExists([$media->path, $media->card_path]);
    }

    Queue::assertPushed(ReconcileEventSearchIndex::class, fn ($job) => $job->eventId === $event->id);
});

it('rejects local times that disappear during a daylight-saving transition', function () {
    Storage::fake('public');

    $this->from('/admin/events/create')->post('/admin/events', validEventInput([
        'starts_at_local' => '2026-03-29T02:30',
        'ends_at_local' => '2026-03-29T04:00',
        'images' => [
            UploadedFile::fake()->image('cover.jpg'),
            UploadedFile::fake()->image('detail.jpg'),
        ],
    ]))
        ->assertRedirect('/admin/events/create')
        ->assertSessionHasErrors('starts_at_local');

    expect(Event::query()->where('title', 'Admin-created summer meetup')->exists())->toBeFalse();
});

it('requires an explicit UTC offset when a local time occurs twice', function () {
    Storage::fake('public');

    $this->from('/admin/events/create')->post('/admin/events', validEventInput([
        'starts_at_local' => '2026-10-25T02:30',
        'ends_at_local' => '2026-10-25T04:00',
        'images' => [
            UploadedFile::fake()->image('cover.jpg'),
            UploadedFile::fake()->image('detail.jpg'),
        ],
    ]))
        ->assertRedirect('/admin/events/create')
        ->assertSessionHasErrors('starts_at_local_offset');
});

it('requires coordinates before an event can be publicly visible', function () {
    Storage::fake('public');

    $this->from('/admin/events/create')->post('/admin/events', validEventInput([
        'status' => 'published',
        'latitude' => null,
        'longitude' => null,
        'images' => [
            UploadedFile::fake()->image('cover.jpg'),
            UploadedFile::fake()->image('detail.jpg'),
        ],
    ]))
        ->assertRedirect('/admin/events/create')
        ->assertSessionHasErrors(['latitude', 'longitude']);
});

it('does not publish an existing draft without coordinates', function () {
    $event = Event::factory()->withoutCoordinates()->create(['status' => 'draft']);

    $this->from("/admin/events/{$event->id}/edit")
        ->put("/admin/events/{$event->id}", validEventInput([
            'status' => 'published',
            'latitude' => null,
            'longitude' => null,
        ]))
        ->assertRedirect("/admin/events/{$event->id}/edit")
        ->assertSessionHasErrors(['latitude', 'longitude']);

    expect($event->refresh()->status->value)->toBe('draft');
});

it('only permanently deletes drafts without attendance history', function () {
    Queue::fake();
    $draft = Event::factory()->create(['status' => 'draft']);

    $this->delete("/admin/events/{$draft->id}")->assertRedirect('/admin/events');
    $this->assertDatabaseMissing('events', ['id' => $draft->id]);

    $usedDraft = Event::factory()->create(['status' => 'draft']);
    EventAttendance::query()->create([
        'event_id' => $usedDraft->id,
        'user_id' => User::factory()->create()->id,
        'intent' => 'interested',
    ]);

    $this->from("/admin/events/{$usedDraft->id}")
        ->delete("/admin/events/{$usedDraft->id}")
        ->assertRedirect("/admin/events/{$usedDraft->id}")
        ->assertSessionHasErrors('event');

    $this->assertDatabaseHas('events', ['id' => $usedDraft->id]);
});

it('keeps permanent Mapbox geocoding behind the admin boundary', function () {
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

    $this->getJson('/admin/address-search?q=Alexanderplatz%201')
        ->assertOk()
        ->assertJsonPath('results.0.formatted_address', 'Alexanderplatz 1, 10178 Berlin, Germany')
        ->assertJsonPath('results.0.country_code', 'DE')
        ->assertJsonPath('results.0.latitude', 52.5219);

    Http::assertSent(fn ($request) => $request['permanent'] === 'true'
        && $request['access_token'] === 'secret-server-token'
        && $request['autocomplete'] === 'false');
});

/** @param array<string, mixed> $overrides */
function validEventInput(array $overrides = []): array
{
    return array_replace([
        'title' => 'Admin-created summer meetup',
        'description' => 'A complete event created through the administration workspace.',
        'organizer_name' => 'Event Visuals',
        'venue_name' => 'Alexanderplatz Hall',
        'formatted_address' => 'Alexanderplatz 1, 10178 Berlin, Germany',
        'address_line_1' => 'Alexanderplatz 1',
        'postal_code' => '10178',
        'locality' => 'Berlin',
        'region' => 'Berlin',
        'country' => 'Germany',
        'country_code' => 'DE',
        'latitude' => '52.5219',
        'longitude' => '13.4132',
        'timezone' => 'Europe/Berlin',
        'starts_at_local' => '2026-08-14T18:00',
        'ends_at_local' => '2026-08-14T21:00',
        'status' => 'draft',
        'type' => 'meetup',
        'tags' => ['community', 'summer'],
        'minimum_price' => '0.00',
        'currency_code' => 'EUR',
        'capacity' => '120',
    ], $overrides);
}
