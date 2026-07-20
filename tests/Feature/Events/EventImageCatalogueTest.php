<?php

use App\Domain\Events\EventType;
use App\Domain\Events\ImageRole;
use App\Models\Event;
use App\Models\EventImage;
use App\Models\EventImageSet;
use App\Services\Events\EventImageCatalogueImporter;
use App\Services\Events\EventImageManifest;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('imports the checked local image manifest exactly', function () {
    $result = app(EventImageCatalogueImporter::class)->replace();

    expect($result)->toBe(['sets' => 16, 'images' => 32])
        ->and(EventImageSet::query()->count())->toBe(16)
        ->and(EventImage::query()->count())->toBe(32);

    foreach (EventType::values() as $type) {
        expect(EventImageSet::query()->where('category', $type)->count())->toBe(2);
    }

    foreach (EventImageSet::query()->with('images')->get() as $set) {
        expect($set->images->pluck('role')->map->value->sort()->values()->all())
            ->toBe(ImageRole::values());
    }
});

it('replaces the image catalogue idempotently', function () {
    $importer = app(EventImageCatalogueImporter::class);

    expect($importer->replace())->toBe(['sets' => 16, 'images' => 32])
        ->and($importer->replace())->toBe(['sets' => 16, 'images' => 32])
        ->and(EventImageSet::query()->count())->toBe(16)
        ->and(EventImage::query()->count())->toBe(32);
});

it('rolls back replacement when an event references a stale set', function () {
    $importer = app(EventImageCatalogueImporter::class);
    $importer->replace();

    EventImageSet::query()->create(['key' => 'stale-concert-set', 'category' => 'concert']);
    Event::factory()->create([
        'type' => 'concert',
        'image_set_key' => 'stale-concert-set',
    ]);
    EventImage::query()->where('role', 'cover')->firstOrFail()->update(['alt' => 'rollback marker']);

    expect(fn () => $importer->replace())->toThrow(QueryException::class)
        ->and(EventImageSet::query()->whereKey('stale-concert-set')->exists())->toBeTrue()
        ->and(EventImage::query()->where('alt', 'rollback marker')->exists())->toBeTrue()
        ->and(EventImageSet::query()->count())->toBe(17)
        ->and(EventImage::query()->count())->toBe(32);
});

it('rejects malformed or unsafe image manifests', function () {
    $contents = file_get_contents(public_path('images/events/manifest.json'));
    expect($contents)->not->toBeFalse();

    $original = json_decode((string) $contents, true, 512, JSON_THROW_ON_ERROR);
    $mutations = [
        'hash mismatch' => function (array &$manifest): void {
            $manifest['sets'][0]['images'][0]['sha256'] = str_repeat('0', 64);
        },
        'missing role' => function (array &$manifest): void {
            array_pop($manifest['sets'][0]['images']);
        },
        'wrong set count' => function (array &$manifest): void {
            array_pop($manifest['sets']);
        },
        'unknown category' => function (array &$manifest): void {
            $manifest['sets'][0]['category'] = 'unknown';
        },
        'unsafe path' => function (array &$manifest): void {
            $manifest['sets'][0]['images'][0]['path'] = '/images/events/../secret.webp';
        },
    ];

    foreach ($mutations as $mutate) {
        $manifest = $original;
        $mutate($manifest);
        $path = tempnam(storage_path('framework/testing'), 'event-manifest-');
        expect($path)->not->toBeFalse();
        file_put_contents((string) $path, json_encode($manifest, JSON_THROW_ON_ERROR));

        try {
            expect(fn () => (new EventImageManifest((string) $path))->read())
                ->toThrow(RuntimeException::class);
        } finally {
            unlink((string) $path);
        }
    }
});
