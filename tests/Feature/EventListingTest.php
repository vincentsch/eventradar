<?php

use App\Models\Event;
use App\Models\User;
use App\Services\Events\EventImageCatalogueImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

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
