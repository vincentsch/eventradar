<?php

use App\Models\Event;
use App\Services\Events\EventImageCatalogueImporter;
use App\Services\Search\EventSearchDocument;
use App\Services\Search\EventSearchIndexer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeEventSearchGateway;

uses(RefreshDatabase::class);

beforeEach(function () {
    Date::setTestNow('2026-07-20 12:00:00');
    app(EventImageCatalogueImporter::class)->replace();
    config()->set([
        'meilisearch.event_index' => 'test_events',
        'meilisearch.database_chunk_size' => 2,
        'meilisearch.batch_document_limit' => 1,
        'meilisearch.batch_byte_limit' => 1_000_000,
        'meilisearch.build_timeout_ms' => 900_000,
        'meilisearch.reconcile_timeout_ms' => 12_000,
    ]);
});

afterEach(fn () => Date::setTestNow());

it('queues all large-build batches before waiting and atomically replaces the logical index', function () {
    createSearchableEvents(3);
    Event::factory()->create(['status' => 'draft']);
    Event::factory()->published()->ended()->create();

    $gateway = new FakeEventSearchGateway;
    $gateway->indexes['test_events'] = [
        'documents' => ['old-id' => ['id' => 'old-id']],
        'settings' => [],
    ];
    $result = searchIndexer($gateway)->rebuild(CarbonImmutable::parse('2026-07-20 12:00:00', 'UTC'));

    $documentTaskUids = range(
        (int) $result['first_document_task_uid'],
        (int) $result['last_document_task_uid'],
    );
    $waitedUids = array_column($gateway->waits, 'uid');
    $settingsPosition = operationPosition($gateway->operations, fn (string $operation) => str_starts_with($operation, 'settings:'));
    $documentsPosition = operationPosition($gateway->operations, fn (string $operation) => str_starts_with($operation, 'documents:'));
    $swapPosition = operationPosition($gateway->operations, fn (string $operation) => str_starts_with($operation, 'swap:'));
    $deletePosition = operationPosition($gateway->operations, fn (string $operation) => str_starts_with($operation, 'delete:'));

    expect($result['documents_submitted'])->toBe(3)
        ->and($result['batches_submitted'])->toBe(3)
        ->and($result['document_task_count'])->toBe(3)
        ->and($gateway->bulkTaskLookups)->toBe([$documentTaskUids])
        ->and($waitedUids)->toContain($documentTaskUids[2])
        ->and($waitedUids)->not->toContain($documentTaskUids[0], $documentTaskUids[1])
        ->and($settingsPosition)->toBeLessThan($documentsPosition)
        ->and($documentsPosition)->toBeLessThan($swapPosition)
        ->and($swapPosition)->toBeLessThan($deletePosition)
        ->and($gateway->indexes)->toHaveKey('test_events')
        ->and($gateway->indexes['test_events']['documents'])->toHaveCount(3)
        ->and($gateway->indexes)->toHaveCount(1)
        ->and($result['cleanup']['status'])->toBe('deleted');
});

it('bulk-checks every submitted task and refuses to swap after an earlier batch fails', function () {
    createSearchableEvents(3);
    $gateway = new FakeEventSearchGateway;
    $gateway->failDocumentTaskNumber = 1;

    expect(fn () => searchIndexer($gateway)->rebuild(CarbonImmutable::parse('2026-07-20 12:00:00', 'UTC')))
        ->toThrow(RuntimeException::class, 'simulated document failure');

    expect($gateway->bulkTaskLookups)->toHaveCount(1)
        ->and($gateway->operations)->not->toContain('swap:test_events')
        ->and(collect($gateway->operations)->contains(fn (string $operation) => str_starts_with($operation, 'swap:')))->toBeFalse()
        ->and(collect($gateway->operations)->contains(fn (string $operation) => str_starts_with($operation, 'delete:')))->toBeFalse();
});

it('refuses promotion when the authoritative source changes during the build', function () {
    $event = createSearchableEvents(2)->firstOrFail();
    $gateway = new FakeEventSearchGateway;
    $changed = false;
    $gateway->afterDocumentsAdded = function () use ($event, &$changed): void {
        if ($changed) {
            return;
        }

        DB::table('events')->where('id', $event->id)->update([
            'title' => 'Changed during build',
        ]);
        $changed = true;
    };

    expect(fn () => searchIndexer($gateway)->rebuild(CarbonImmutable::parse('2026-07-20 12:00:00', 'UTC')))
        ->toThrow(RuntimeException::class, 'source changed during indexing');

    expect(collect($gateway->operations)->contains(fn (string $operation) => str_starts_with($operation, 'swap:')))->toBeFalse();
});

it('refuses promotion when the temporary index count is wrong', function () {
    createSearchableEvents(2);
    $gateway = new FakeEventSearchGateway;
    $gateway->corruptStats = true;

    expect(fn () => searchIndexer($gateway)->rebuild(CarbonImmutable::parse('2026-07-20 12:00:00', 'UTC')))
        ->toThrow(RuntimeException::class, 'contains 3 documents; expected 2');

    expect(collect($gateway->operations)->contains(fn (string $operation) => str_starts_with($operation, 'swap:')))->toBeFalse();
});

it('refuses promotion when a temporary representative document is wrong', function () {
    createSearchableEvents(2);
    $gateway = new FakeEventSearchGateway;
    $gateway->corruptDocumentReadNumber = 1;

    expect(fn () => searchIndexer($gateway)->rebuild(CarbonImmutable::parse('2026-07-20 12:00:00', 'UTC')))
        ->toThrow(RuntimeException::class, 'representative document validation');

    expect(collect($gateway->operations)->contains(fn (string $operation) => str_starts_with($operation, 'swap:')))->toBeFalse();
});

it('does not delete either index when the asynchronous swap task fails', function () {
    createSearchableEvents(1);
    $gateway = new FakeEventSearchGateway;
    $gateway->failSwap = true;

    expect(fn () => searchIndexer($gateway)->rebuild(CarbonImmutable::parse('2026-07-20 12:00:00', 'UTC')))
        ->toThrow(RuntimeException::class, 'simulated swap failure');

    expect(collect($gateway->operations)->contains(fn (string $operation) => str_starts_with($operation, 'delete:')))->toBeFalse()
        ->and($gateway->indexes)->toHaveCount(2);
});

it('keeps the displaced index when post-swap sample validation fails', function () {
    createSearchableEvents(3);
    $gateway = new FakeEventSearchGateway;
    $gateway->indexes['test_events'] = ['documents' => [], 'settings' => []];
    $gateway->corruptDocumentReadNumber = 4;

    expect(fn () => searchIndexer($gateway)->rebuild(CarbonImmutable::parse('2026-07-20 12:00:00', 'UTC')))
        ->toThrow(RuntimeException::class, 'representative document validation');

    expect(collect($gateway->operations)->contains(fn (string $operation) => str_starts_with($operation, 'swap:')))->toBeTrue()
        ->and(collect($gateway->operations)->contains(fn (string $operation) => str_starts_with($operation, 'delete:')))->toBeFalse()
        ->and($gateway->indexes)->toHaveCount(2);
});

it('keeps the promoted index successful when displaced cleanup fails', function () {
    createSearchableEvents(2);
    $gateway = new FakeEventSearchGateway;
    $gateway->failCleanup = true;

    $result = searchIndexer($gateway)->rebuild(CarbonImmutable::parse('2026-07-20 12:00:00', 'UTC'));

    expect($result['cleanup']['status'])->toBe('warning')
        ->and($gateway->indexes['test_events']['documents'])->toHaveCount(2)
        ->and($gateway->indexes)->toHaveCount(2);
});

it('handles an empty public catalogue without waiting for a nonexistent document task', function () {
    Event::factory()->create(['status' => 'draft']);
    $gateway = new FakeEventSearchGateway;

    $result = searchIndexer($gateway)->rebuild(CarbonImmutable::parse('2026-07-20 12:00:00', 'UTC'));

    expect($result['source_count'])->toBe(0)
        ->and($result['document_task_count'])->toBe(0)
        ->and($gateway->bulkTaskLookups)->toBe([])
        ->and($gateway->indexes['test_events']['documents'])->toBe([]);
});

it('reconciles one event with a short bounded task wait', function () {
    $published = createSearchableEvents(1)->firstOrFail();
    $draft = Event::factory()->create(['status' => 'draft']);
    $gateway = new FakeEventSearchGateway;
    $gateway->indexes['test_events'] = ['documents' => [], 'settings' => []];
    $indexer = searchIndexer($gateway);

    $upsert = $indexer->reconcile($published->id, CarbonImmutable::parse('2026-07-20 12:00:00', 'UTC'));
    $remove = $indexer->reconcile($draft->id, CarbonImmutable::parse('2026-07-20 12:00:00', 'UTC'));
    $missing = $indexer->reconcile('01981f4c-3c00-7000-8000-000000000099', CarbonImmutable::parse('2026-07-20 12:00:00', 'UTC'));

    expect($upsert['action'])->toBe('upserted')
        ->and($remove['action'])->toBe('removed')
        ->and($missing['action'])->toBe('removed')
        ->and($gateway->indexes['test_events']['documents'])->toHaveKey($published->id)
        ->and(array_column($gateway->waits, 'timeout'))->each->toBe(12_000);
});

/** @return Collection<int, Event> */
function createSearchableEvents(int $count)
{
    return Event::factory()->count($count)->published()->create([
        'starts_at' => '2026-08-01 18:00:00',
        'ends_at' => '2026-08-01 21:00:00',
        'starts_on_local' => '2026-08-01',
        'updated_at' => '2026-07-20 12:00:00',
    ]);
}

function searchIndexer(FakeEventSearchGateway $gateway): EventSearchIndexer
{
    return new EventSearchIndexer($gateway, new EventSearchDocument);
}

/**
 * @param  list<string>  $operations
 * @param  callable(string): bool  $matches
 */
function operationPosition(array $operations, callable $matches): int
{
    foreach ($operations as $position => $operation) {
        if ($matches($operation)) {
            return $position;
        }
    }

    throw new RuntimeException('Expected search operation was not recorded.');
}
