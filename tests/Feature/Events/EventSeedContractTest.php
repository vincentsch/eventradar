<?php

use App\Services\Events\DeterministicEventGenerator;
use App\Services\Events\EventImageManifest;
use App\Services\Events\EventSeedOptions;
use Carbon\CarbonImmutable;
use Database\Seeders\EventSeeder;

it('uses the ten thousand row development profile by default', function () {
    config()->set('events.seed_profile', 'dev');
    config()->set('events.seed_profiles.dev', 10_000);
    config()->set('events.seed', 'eventradar-v1');
    config()->set('events.seed_reference_at', '2026-07-20T12:00:00Z');

    $options = EventSeedOptions::fromConfig();

    expect($options->profile)->toBe('dev')
        ->and($options->rowCount)->toBe(10_000)
        ->and($options->referenceAt->toIso8601ZuluString())->toBe('2026-07-20T12:00:00Z');
});

it('requires explicit acknowledgement for the full seed profile', function () {
    config()->set('events.seed_profile', 'full');
    config()->set('events.allow_full_seed', false);

    expect(fn () => EventSeedOptions::fromConfig())
        ->toThrow(LogicException::class, 'EVENT_SEED_ALLOW_FULL=true');
});

it('rejects unknown profiles and reference instants without an offset', function (array $changes, string $message) {
    config()->set($changes);

    expect(fn () => EventSeedOptions::fromConfig())
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'unknown profile' => [[
        'events.seed_profile' => 'huge',
    ], 'EVENT_SEED_PROFILE'],
    'ambiguous reference instant' => [[
        'events.seed_reference_at' => '2026-07-20 12:00:00',
    ], 'explicit offset'],
]);

it('derives insert batch size from the placeholder budget', function () {
    $columns = count(EventSeeder::INSERT_COLUMNS);
    $batchSize = EventSeeder::batchSizeForPlaceholderBudget(60_000);

    expect($batchSize)->toBe(2_222)
        ->and($batchSize * $columns)->toBeLessThanOrEqual(60_000)
        ->and(($batchSize + 1) * $columns)->toBeGreaterThan(60_000)
        ->and(EventSeeder::batchSizeForPlaceholderBudget(30_000))->toBe(1_111);
});

it('generates stable normalized rows independent of call order', function () {
    $options = new EventSeedOptions(
        profile: 'smoke',
        rowCount: 500,
        seed: 'contract-test-v1',
        referenceAt: CarbonImmutable::parse('2026-07-20T12:00:00Z'),
    );
    $sets = collect((new EventImageManifest)->read()['sets'])
        ->groupBy('category')
        ->map(fn ($group): array => $group->pluck('key')->sort()->values()->all())
        ->all();
    $locations = require database_path('data/gazetteer.php');
    $generator = new DeterministicEventGenerator;

    $later = $generator->row(499, $options, [10, 11], $sets, $locations);
    $first = $generator->row(0, $options, [10, 11], $sets, $locations);
    $again = $generator->row(499, $options, [10, 11], $sets, $locations);

    expect($again)->toBe($later)
        ->and(array_keys($first))->toBe(EventSeeder::INSERT_COLUMNS)
        ->and($first['id'][14])->toBe('7')
        ->and($first['id'])->toBeLessThan($later['id'])
        ->and(strlen((string) $first['payload']))->toBe((int) config('events.seed_payload_bytes'))
        ->and($first['starts_on_local'])->toBe(
            CarbonImmutable::parse((string) $first['starts_at'], 'UTC')
                ->setTimezone((string) $first['timezone'])
                ->toDateString(),
        );
});
