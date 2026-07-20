<?php

use App\Models\Event;
use Carbon\CarbonImmutable;
use Database\Seeders\EventSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    expect(DB::connection()->getDriverName())->toBe('mysql');
    config()->set('events.seed_profile', 'smoke');
    config()->set('events.seed', 'mysql-contract-v1');
    config()->set('events.seed_reference_at', '2026-07-20T12:00:00Z');
    config()->set('events.allow_full_seed', false);
    config()->set('events.seed_demo_admin', true);
});

it('requires an explicit opt-in for the local demo administrator', function () {
    config()->set('events.seed_demo_admin', false);

    $this->seed(EventSeeder::class);

    $owner = DB::table('users')
        ->where('email', 'event-owner-001@example.test')
        ->first(['password']);

    expect(DB::table('users')->where('email', 'reviewer@example.test')->exists())->toBeFalse()
        ->and($owner)->not->toBeNull()
        ->and(Hash::check('password', $owner->password))->toBeFalse();
});

it('seeds the deterministic smoke profile and refuses accidental append', function () {
    $this->seed(EventSeeder::class);

    expect(Event::query()->count())->toBe(500)
        ->and(DB::table('event_image_sets')->count())->toBe(16)
        ->and(DB::table('event_images')->count())->toBe(32)
        ->and(DB::table('users')->where('email', 'reviewer@example.test')->exists())->toBeTrue()
        ->and(DB::table('users')->where('email', 'like', 'event-owner-%@example.test')->count())->toBe(128);

    $events = DB::table('events')
        ->orderBy('id')
        ->get(['id', 'title', 'starts_at', 'starts_on_local', 'timezone', 'image_set_key', 'payload']);
    $checksum = hash('sha256', $events->map(
        fn ($event): string => implode('|', (array) $event),
    )->implode("\n"));

    foreach ($events as $event) {
        expect($event->id[14])->toBe('7')
            ->and(strlen($event->payload))->toBe((int) config('events.seed_payload_bytes'))
            ->and($event->starts_on_local)->toBe(
                CarbonImmutable::parse($event->starts_at, 'UTC')
                    ->setTimezone($event->timezone)
                    ->toDateString(),
            );
    }

    expect(fn () => $this->seed(EventSeeder::class))
        ->toThrow(RuntimeException::class, 'Refusing to append');

    DB::table('events')->delete();
    $this->seed(EventSeeder::class);

    $repeatedChecksum = hash('sha256', DB::table('events')
        ->orderBy('id')
        ->get(['id', 'title', 'starts_at', 'starts_on_local', 'timezone', 'image_set_key', 'payload'])
        ->map(fn ($event): string => implode('|', (array) $event))
        ->implode("\n"));

    expect($repeatedChecksum)->toBe($checksum);
});

it('rejects the full profile before changing the database', function () {
    config()->set('events.seed_profile', 'full');

    expect(fn () => $this->seed(EventSeeder::class))
        ->toThrow(LogicException::class, 'EVENT_SEED_ALLOW_FULL=true')
        ->and(DB::table('events')->count())->toBe(0)
        ->and(DB::table('event_image_sets')->count())->toBe(0);
});
