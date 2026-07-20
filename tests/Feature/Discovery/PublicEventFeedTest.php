<?php

use App\Models\Event;
use App\Services\Discovery\PublicEventFeed;
use App\Services\Events\EventImageCatalogueImporter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

uses(RefreshDatabase::class);

beforeEach(function () {
    Date::setTestNow('2026-07-20 12:00:00 UTC');
    app(EventImageCatalogueImporter::class)->replace();
});

afterEach(fn () => Date::setTestNow());

it('cursor-paginates every visible state in stable chronological order without overlap', function () {
    $instant = CarbonImmutable::parse('2026-07-20 12:00:00', 'UTC');
    $visible = collect();

    for ($ordinal = 0; $ordinal < 25; $ordinal++) {
        $startsAt = $instant->addDays($ordinal + 1);
        $visible->push(Event::factory()->create([
            'status' => $ordinal % 2 === 0 ? 'published' : 'sold_out',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHours(2),
            'starts_on_local' => $startsAt->toDateString(),
        ]));
    }

    Event::factory()->create(['status' => 'draft']);
    Event::factory()->published()->ended()->create();

    $feed = app(PublicEventFeed::class);
    $first = $feed->page($instant, null);
    $second = $feed->page($instant, $first->nextCursor);
    $firstIds = collect($first->events)->pluck('id');
    $secondIds = collect($second->events)->pluck('id');

    expect($first->events)->toHaveCount(18)
        ->and($second->events)->toHaveCount(7)
        ->and($firstIds->intersect($secondIds))->toBeEmpty()
        ->and($firstIds->merge($secondIds)->values()->all())->toBe($visible->pluck('id')->all())
        ->and($first->payload())->not->toHaveKey('total');
});

it('includes ongoing events and applies the exact exclusive end boundary', function () {
    $instant = CarbonImmutable::parse('2026-07-20 12:00:00', 'UTC');
    $ongoing = Event::factory()->published()->create([
        'starts_at' => $instant->subHours(71),
        'ends_at' => $instant->addSecond(),
        'starts_on_local' => $instant->subHours(71)->toDateString(),
    ]);
    Event::factory()->published()->create([
        'starts_at' => $instant->subHours(2),
        'ends_at' => $instant,
        'starts_on_local' => $instant->subHours(2)->toDateString(),
    ]);

    $events = app(PublicEventFeed::class)->page($instant, null)->events;

    expect(collect($events)->pluck('id')->all())->toBe([$ongoing->id]);
});
