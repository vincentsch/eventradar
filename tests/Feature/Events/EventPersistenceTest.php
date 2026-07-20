<?php

use App\Domain\Events\EventStatus;
use App\Domain\Events\EventType;
use App\Models\Event;
use App\Models\User;
use App\Services\Events\EventImageCatalogueImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => app(EventImageCatalogueImporter::class)->replace());

it('casts normalized fields while retaining raw payload text', function () {
    $event = Event::factory()->published()->create(['payload' => '{"source":"raw"}']);
    $fresh = $event->fresh();

    expect($fresh)
        ->not->toBeNull()
        ->and($fresh->status)->toBe(EventStatus::Published)
        ->and($fresh->type)->toBeInstanceOf(EventType::class)
        ->and($fresh->tags)->toBeArray()
        ->and($fresh->latitude)->toBeFloat()
        ->and($fresh->payload)->toBe('{"source":"raw"}');
});

it('retains an event when its optional owner is deleted', function () {
    $owner = User::factory()->create();
    $event = Event::factory()->for($owner)->create();

    $owner->delete();

    expect(Event::query()->findOrFail($event->id)->user_id)->toBeNull();
});

it('does not mass assign provenance, ownership, lifecycle, or identifier fields', function () {
    $event = new Event;

    expect($event->getFillable())->not->toContain(
        'id',
        'user_id',
        'status',
        'payload',
        'created_at',
        'updated_at',
    )->and($event->getHidden())->toContain('payload', 'user_id');
});

it('keeps all named factory states schema-valid', function (string $state) {
    $event = Event::factory()->{$state}()->create();

    expect($event->fresh())->not->toBeNull();
})->with([
    'published',
    'soldOut',
    'ended',
    'ongoing',
    'withoutCoordinates',
    'free',
    'withoutCapacity',
]);
