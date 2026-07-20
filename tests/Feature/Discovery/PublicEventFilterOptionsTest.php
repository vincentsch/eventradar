<?php

use App\Models\Event;
use App\Services\Discovery\PublicEventFilterOptions;
use App\Services\Events\EventImageCatalogueImporter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

uses(RefreshDatabase::class);

beforeEach(function () {
    Date::setTestNow('2026-07-20 12:00:00 UTC');
    Cache::clear();
    app(EventImageCatalogueImporter::class)->replace();
});

afterEach(fn () => Date::setTestNow());

it('caches distinct visible locations while excluding private and ended rows', function () {
    $instant = CarbonImmutable::parse('2026-07-20 12:00:00', 'UTC');
    Event::factory()->published()->count(2)->create([
        'location_key' => 'de-berlin',
        'locality' => 'Berlin',
        'country' => 'Germany',
        'starts_at' => '2026-08-01 18:00:00',
        'ends_at' => '2026-08-01 20:00:00',
        'starts_on_local' => '2026-08-01',
    ]);
    Event::factory()->soldOut()->create([
        'location_key' => 'de-hamburg',
        'locality' => 'Hamburg',
        'region' => 'Hamburg',
        'country' => 'Germany',
        'starts_at' => '2026-08-02 18:00:00',
        'ends_at' => '2026-08-02 20:00:00',
        'starts_on_local' => '2026-08-02',
    ]);
    Event::factory()->create(['status' => 'draft', 'location_key' => 'private']);
    Event::factory()->published()->ended()->create(['location_key' => 'ended']);

    $options = app(PublicEventFilterOptions::class);

    expect($options->locations($instant))->toBe([
        ['value' => 'de-berlin', 'label' => 'Berlin, Germany'],
        ['value' => 'de-hamburg', 'label' => 'Hamburg, Germany'],
    ]);

    Event::query()->delete();

    expect($options->locations($instant))->toHaveCount(2);
});
