<?php

use App\Models\Event;
use App\Models\User;
use App\Services\Events\EventImageCatalogueImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

uses(RefreshDatabase::class);

beforeEach(fn () => app(EventImageCatalogueImporter::class)->replace());
afterEach(fn () => Date::setTestNow());

it('renders the events listing shell without authentication', function () {
    $this->get(route('events.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Events/Index')
            ->where('statuses', ['published', 'sold_out'])
            ->where('filters.from', null)
        );
});

it('returns a compact normalized event page without payload or owner data', function () {
    Date::setTestNow('2026-08-20 12:00:00');
    $user = User::factory()->create([
        'name' => 'Ada Lovelace',
        'email' => 'private-owner@example.test',
    ]);
    Event::factory()->for($user)->published()->create([
        'title' => 'Global Tech Summit',
        'type' => 'conference',
        'starts_at' => '2026-08-21 18:00:00',
        'ends_at' => '2026-08-21 20:00:00',
        'starts_on_local' => '2026-08-21',
        'latitude' => 52.5200,
        'longitude' => 13.4050,
        'payload' => '{"private":"source"}',
    ]);

    $this->getJson(route('events.data'))
        ->assertOk()
        ->assertJsonStructure([
            'data',
            'current_page',
            'last_page',
            'total',
            'stats' => ['ms', 'bytes'],
        ])
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.title', 'Global Tech Summit')
        ->assertJsonPath('data.0.type', 'conference')
        ->assertJsonMissingPath('data.0.payload')
        ->assertJsonMissingPath('data.0.user_id')
        ->assertJsonMissingPath('data.0.user')
        ->assertJsonMissing(['private-owner@example.test']);
});

it('filters visible events by normalized status and local start date', function () {
    Date::setTestNow('2026-08-20 12:00:00');
    Event::factory()->published()->create([
        'starts_at' => '2026-08-20 10:00:00',
        'ends_at' => '2026-08-20 14:00:00',
        'starts_on_local' => '2026-08-20',
    ]);
    Event::factory()->soldOut()->create([
        'starts_at' => '2026-08-21 18:00:00',
        'ends_at' => '2026-08-21 20:00:00',
        'starts_on_local' => '2026-08-22',
    ]);
    Event::factory()->create([
        'status' => 'cancelled',
        'starts_at' => '2026-08-21 18:00:00',
        'ends_at' => '2026-08-21 20:00:00',
        'starts_on_local' => '2026-08-21',
    ]);

    $this->getJson(route('events.data', [
        'status' => 'sold_out',
        'from' => '2026-08-21',
    ]))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'sold_out')
        ->assertJsonFragment(['starts_on_local' => '2026-08-22']);
});

it('never lists drafts, cancellations, or ended events', function () {
    Date::setTestNow('2026-08-20 12:00:00');
    Event::factory()->published()->ongoing()->create(['title' => 'Visible published']);
    Event::factory()->soldOut()->ongoing()->create(['title' => 'Visible sold out']);
    Event::factory()->ongoing()->create(['status' => 'draft', 'title' => 'Hidden draft']);
    Event::factory()->ongoing()->create(['status' => 'cancelled', 'title' => 'Hidden cancelled']);
    Event::factory()->published()->ended()->create(['title' => 'Hidden ended']);

    $this->getJson(route('events.data', ['status' => 'cancelled']))
        ->assertOk()
        ->assertJsonPath('total', 2)
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['title' => 'Visible published'])
        ->assertJsonFragment(['title' => 'Visible sold out'])
        ->assertJsonMissing(['title' => 'Hidden draft'])
        ->assertJsonMissing(['title' => 'Hidden cancelled'])
        ->assertJsonMissing(['title' => 'Hidden ended']);
});

it('ignores impossible local dates instead of comparing them differently by database', function () {
    $this->get(route('events.index', ['from' => '2026-13-99']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('filters.from', null));
});

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
