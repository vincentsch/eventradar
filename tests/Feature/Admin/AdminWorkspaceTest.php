<?php

use App\Models\Event;
use App\Models\User;
use App\Services\Events\EventImageCatalogueImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(EventImageCatalogueImporter::class)->replace();
    $this->actingAs(User::factory()->create());
});

it('renders database-backed dashboard aggregates', function () {
    Event::factory()->count(2)->create(['status' => 'draft', 'type' => 'meetup']);
    Event::factory()->count(3)->create(['status' => 'published', 'type' => 'concert']);
    Event::factory()->create(['status' => 'cancelled', 'type' => 'concert']);

    $this->get('/admin')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->where('summary.total', 6)
            ->where('summary.statuses.draft', 2)
            ->where('summary.statuses.published', 3)
            ->where('summary.statuses.cancelled', 1)
            ->where('summary.types.meetup', 2)
            ->where('summary.types.concert', 4)
        );
});

it('paginates every lifecycle state in deterministic fifty-row pages', function () {
    Event::factory()->count(25)->create(['status' => 'draft']);
    Event::factory()->count(25)->create(['status' => 'cancelled']);
    Event::factory()->count(25)->create(['status' => 'published']);

    $this->get('/admin/events')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Events/Index')
            ->has('events.data', 50)
            ->where('events.current_page', 1)
            ->where('events.last_page', 2)
            ->where('events.total', 75)
            ->where('events.from', 1)
            ->where('events.to', 50)
            ->where('events.next_page_url', fn ($url) => is_string($url) && str_contains($url, 'page=2'))
        );

    $this->get('/admin/events?page=2')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('events.data', 25)
            ->where('events.current_page', 2)
            ->where('events.from', 51)
            ->where('events.to', 75)
        );
});

it('applies canonical URL filters and exact UUID lookup', function () {
    $target = Event::factory()->create([
        'title' => 'Alpine Design Forum',
        'status' => 'cancelled',
        'type' => 'conference',
        'country_code' => 'CH',
        'country' => 'Switzerland',
        'starts_on_local' => '2026-09-10',
    ]);
    Event::factory()->create([
        'title' => 'Alpine Design Forum Later',
        'status' => 'published',
        'type' => 'conference',
        'country_code' => 'CH',
        'country' => 'Switzerland',
        'starts_on_local' => '2026-10-10',
    ]);

    $this->get('/admin/events?'.http_build_query([
        'q' => 'Alpine',
        'status' => 'cancelled',
        'type' => 'conference',
        'country_code' => 'ch',
        'from' => '2026-09-01',
        'to' => '2026-09-30',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('events.data', 1)
            ->where('events.data.0.id', $target->id)
            ->where('filters.country_code', 'CH')
        );

    $this->get('/admin/events?q='.$target->id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('events.data', 1)
            ->where('events.data.0.id', $target->id)
        );
});

it('uses the event identifier as a stable tie-break for equal start times', function () {
    $lower = Event::factory()->create([
        'id' => '00000000-0000-7000-8000-000000000001',
        'title' => 'Tie Break Event One',
        'starts_at' => '2026-09-10 18:00:00',
        'ends_at' => '2026-09-10 20:00:00',
        'starts_on_local' => '2026-09-10',
    ]);
    $higher = Event::factory()->create([
        'id' => '00000000-0000-7000-8000-000000000002',
        'title' => 'Tie Break Event Two',
        'starts_at' => '2026-09-10 18:00:00',
        'ends_at' => '2026-09-10 20:00:00',
        'starts_on_local' => '2026-09-10',
    ]);

    $this->get('/admin/events?q=Tie%20Break')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('events.data.0.id', $higher->id)
            ->where('events.data.1.id', $lower->id)
        );
});

it('ignores malformed filters without wildcard or date ambiguity', function () {
    Event::factory()->create();

    $this->get('/admin/events?'.http_build_query([
        'q' => '%%',
        'status' => 'deleted',
        'type' => 'webinar',
        'country_code' => 'ZZ',
        'from' => '2026-12-01',
        'to' => '2026-01-01',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters', [
                'q' => null,
                'status' => null,
                'type' => null,
                'country_code' => null,
                'from' => null,
                'to' => null,
            ])
            ->where('events.total', 1)
        );
});

it('rejects malformed UTF-8 title filters before querying MySQL', function () {
    Event::factory()->create();

    $this->get('/admin/events?q=%FF%FE')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.q', null)
            ->where('events.total', 1)
        );
});

it('inspects hidden lifecycle states without selecting payload or owner data', function () {
    $owner = User::factory()->create(['email' => 'private-owner@example.test']);
    $event = Event::factory()->for($owner)->ended()->create([
        'status' => 'draft',
        'payload' => '{"private":"raw source"}',
    ]);

    $this->get('/admin/events/'.$event->id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Events/Show')
            ->where('event.id', $event->id)
            ->where('event.status', 'draft')
            ->where('event.payload_bytes', strlen('{"private":"raw source"}'))
            ->has('event.images', 2)
            ->missing('event.payload')
            ->missing('event.user_id')
            ->missing('event.user')
        )
        ->assertDontSee('private-owner@example.test')
        ->assertDontSee('raw source');
});
