<?php

namespace App\Services\Search;

use App\Domain\Events\EventStatus;
use App\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class EventSearchIndexer
{
    public function __construct(
        private readonly EventSearchGateway $gateway,
        private readonly EventSearchDocument $documents,
    ) {}

    /** @return array<string, mixed> */
    public function rebuild(?CarbonImmutable $eligibilityInstant = null): array
    {
        $startedAt = microtime(true);
        $eligibilityInstant ??= Date::now('UTC')->toImmutable();
        $logicalIndex = (string) config('meilisearch.event_index', 'events_v1');
        $temporaryIndex = $this->temporaryIndexName($logicalIndex);
        $buildTimeout = (int) config('meilisearch.build_timeout_ms', 1_800_000);
        $sourceBefore = $this->sourceSnapshot($eligibilityInstant);
        $samples = $this->sourceSamples($eligibilityInstant, $sourceBefore['count']);

        $createTask = $this->gateway->createIndex($temporaryIndex, EventSearchDocument::PRIMARY_KEY);
        $this->requireSucceededTask('create temporary index', $this->gateway->waitForTask($createTask, $buildTimeout));

        $settingsTask = $this->gateway->updateSettings($temporaryIndex, $this->documents->settings());
        $this->requireSucceededTask('configure temporary index', $this->gateway->waitForTask($settingsTask, $buildTimeout));

        $batcher = new EventSearchDocumentBatcher(
            documentLimit: (int) config('meilisearch.batch_document_limit', 10_000),
            byteLimit: (int) config('meilisearch.batch_byte_limit', 8 * 1024 * 1024),
        );
        $documentTaskUids = [];
        $documentCount = 0;
        $payloadBytes = 0;
        $batchCount = 0;

        $submitBatch = function (?array $batch) use (
            $temporaryIndex,
            &$documentTaskUids,
            &$documentCount,
            &$payloadBytes,
            &$batchCount,
        ): void {
            if ($batch === null) {
                return;
            }

            $documentTaskUids[] = $this->gateway->addDocumentsJson(
                $temporaryIndex,
                $batch['json'],
                EventSearchDocument::PRIMARY_KEY,
            );
            $documentCount += $batch['count'];
            $payloadBytes += $batch['bytes'];
            $batchCount++;
        };

        $this->eligibleQuery($eligibilityInstant)
            ->select(EventSearchDocument::SOURCE_COLUMNS)
            ->chunkById(
                max(1, (int) config('meilisearch.database_chunk_size', 2_000)),
                function ($events) use ($batcher, $submitBatch): void {
                    foreach ($events as $event) {
                        $submitBatch($batcher->add($this->documents->build($event)));
                    }
                },
            );
        $submitBatch($batcher->flush());

        $lastDocumentTask = null;
        if ($documentTaskUids !== []) {
            $lastDocumentTask = $this->gateway->waitForTask($documentTaskUids[array_key_last($documentTaskUids)], $buildTimeout);
            $this->requireSucceededTask('complete document queue', $lastDocumentTask);
            $this->requireAllTasksSucceeded($documentTaskUids);
        }

        $sourceAfter = $this->sourceSnapshot($eligibilityInstant);
        if ($sourceAfter !== $sourceBefore) {
            throw new RuntimeException(sprintf(
                'The public event source changed during indexing (before %s, after %s); temporary index [%s] was not promoted.',
                json_encode($sourceBefore, JSON_THROW_ON_ERROR),
                json_encode($sourceAfter, JSON_THROW_ON_ERROR),
                $temporaryIndex,
            ));
        }

        if ($documentCount !== $sourceBefore['count']) {
            throw new RuntimeException("Built {$documentCount} documents from {$sourceBefore['count']} eligible events.");
        }

        $this->validateIndex($temporaryIndex, $sourceBefore['count'], $samples);

        $logicalIndexExisted = $this->gateway->indexExists($logicalIndex);
        if (! $logicalIndexExisted) {
            $logicalCreateTask = $this->gateway->createIndex($logicalIndex, EventSearchDocument::PRIMARY_KEY);
            $this->requireSucceededTask('create first logical index', $this->gateway->waitForTask($logicalCreateTask, $buildTimeout));
        }

        $swapTaskUid = $this->gateway->swapIndexes($logicalIndex, $temporaryIndex);
        $swapTask = $this->gateway->waitForTask($swapTaskUid, $buildTimeout);
        $this->requireSucceededTask('swap event indexes', $swapTask);

        $this->validateIndex($logicalIndex, $sourceBefore['count'], $samples);
        $cleanup = $this->cleanupDisplacedIndex($temporaryIndex, $buildTimeout);

        return [
            'logical_index' => $logicalIndex,
            'temporary_index' => $temporaryIndex,
            'logical_index_existed' => $logicalIndexExisted,
            'eligibility_instant' => $eligibilityInstant->toIso8601ZuluString(),
            'source_count' => $sourceBefore['count'],
            'source_watermark' => $sourceBefore['watermark'],
            'source_fingerprint' => $sourceBefore['fingerprint'],
            'documents_submitted' => $documentCount,
            'batches_submitted' => $batchCount,
            'payload_bytes' => $payloadBytes,
            'document_task_count' => count($documentTaskUids),
            'first_document_task_uid' => $documentTaskUids[0] ?? null,
            'last_document_task_uid' => $documentTaskUids[array_key_last($documentTaskUids)] ?? null,
            'last_document_task_duration' => $lastDocumentTask['duration'] ?? null,
            'swap_task_uid' => $swapTaskUid,
            'cleanup' => $cleanup,
            'duration_seconds' => round(microtime(true) - $startedAt, 3),
            'peak_memory_bytes' => memory_get_peak_usage(true),
        ];
    }

    /** @return array<string, mixed> */
    public function reconcile(string $eventId, ?CarbonImmutable $eligibilityInstant = null): array
    {
        $logicalIndex = (string) config('meilisearch.event_index', 'events_v1');
        if (! $this->gateway->indexExists($logicalIndex)) {
            throw new RuntimeException("Event search index [{$logicalIndex}] does not exist; run events:search-index first.");
        }

        $eligibilityInstant ??= Date::now('UTC')->toImmutable();
        $event = Event::query()->find($eventId, EventSearchDocument::SOURCE_COLUMNS);
        $timeout = (int) config('meilisearch.reconcile_timeout_ms', 30_000);

        if ($event !== null && $this->isEligible($event, $eligibilityInstant)) {
            $documents = json_encode(
                [$this->documents->build($event)],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
            $taskUid = $this->gateway->addDocumentsJson($logicalIndex, $documents, EventSearchDocument::PRIMARY_KEY);
            $action = 'upserted';
        } else {
            $taskUid = $this->gateway->deleteDocuments($logicalIndex, [$eventId]);
            $action = 'removed';
        }

        $task = $this->gateway->waitForTask($taskUid, $timeout);
        $this->requireSucceededTask("{$action} event document", $task);

        return [
            'event_id' => $eventId,
            'action' => $action,
            'task_uid' => $taskUid,
        ];
    }

    /** @return Builder<Event> */
    private function eligibleQuery(CarbonImmutable $instant): Builder
    {
        return Event::query()
            ->whereIn('status', EventStatus::publicValues())
            ->where('ends_at', '>', $instant);
    }

    private function isEligible(Event $event, CarbonImmutable $instant): bool
    {
        $statusAttribute = $event->getAttribute('status');
        $status = $statusAttribute instanceof EventStatus ? $statusAttribute->value : (string) $statusAttribute;
        $endsAt = $event->getAttribute('ends_at');

        return in_array($status, EventStatus::publicValues(), true)
            && $endsAt instanceof \DateTimeInterface
            && $endsAt->getTimestamp() > $instant->getTimestamp();
    }

    /** @return array{count: int, watermark: ?string, fingerprint: string} */
    private function sourceSnapshot(CarbonImmutable $instant): array
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return $this->portableSourceSnapshot($instant);
        }

        $fingerprintExpression = <<<'SQL'
            CRC32(JSON_ARRAY(
                id, title, organizer_name, venue_name, starts_at, ends_at, starts_on_local,
                location_key, locality, region, country, country_code, latitude, longitude,
                status, type, tags, minimum_price
            ))
        SQL;
        $snapshot = DB::table('events')
            ->whereIn('status', EventStatus::publicValues())
            ->where('ends_at', '>', $instant)
            ->selectRaw(<<<SQL
                COUNT(*) AS event_count,
                MAX(updated_at) AS watermark,
                BIT_XOR(CAST({$fingerprintExpression} AS UNSIGNED)) AS fingerprint_xor,
                SUM(CAST({$fingerprintExpression} AS UNSIGNED)) AS fingerprint_sum
            SQL)
            ->first();

        return [
            'count' => (int) ($snapshot->event_count ?? 0),
            'watermark' => isset($snapshot->watermark) ? (string) $snapshot->watermark : null,
            'fingerprint' => sprintf(
                '%s:%s',
                isset($snapshot->fingerprint_xor) ? (string) $snapshot->fingerprint_xor : '0',
                isset($snapshot->fingerprint_sum) ? (string) $snapshot->fingerprint_sum : '0',
            ),
        ];
    }

    /** @return array{count: int, watermark: ?string, fingerprint: string} */
    private function portableSourceSnapshot(CarbonImmutable $instant): array
    {
        $hash = hash_init('sha256');
        $count = 0;
        $watermark = null;

        $this->eligibleQuery($instant)
            ->select([...EventSearchDocument::SOURCE_COLUMNS, 'updated_at'])
            ->chunkById(1_000, function ($events) use ($hash, &$count, &$watermark): void {
                foreach ($events as $event) {
                    hash_update($hash, json_encode(
                        $this->documents->build($event),
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                    )."\n");
                    $updatedAt = $event->getRawOriginal('updated_at');
                    if ($updatedAt !== null && ($watermark === null || (string) $updatedAt > $watermark)) {
                        $watermark = (string) $updatedAt;
                    }
                    $count++;
                }
            });

        return [
            'count' => $count,
            'watermark' => $watermark,
            'fingerprint' => hash_final($hash),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function sourceSamples(CarbonImmutable $instant, int $count): array
    {
        if ($count === 0) {
            return [];
        }

        $positions = array_values(array_unique([0, intdiv($count, 2), $count - 1]));
        $samples = [];

        foreach ($positions as $position) {
            $event = $this->eligibleQuery($instant)
                ->select(EventSearchDocument::SOURCE_COLUMNS)
                ->orderBy('id')
                ->offset($position)
                ->firstOrFail();
            $document = $this->documents->build($event);
            $samples[(string) $document['id']] = $document;
        }

        return $samples;
    }

    /** @param array<string, array<string, mixed>> $samples */
    private function validateIndex(string $indexName, int $expectedCount, array $samples): void
    {
        $stats = $this->gateway->stats($indexName);
        $actualCount = (int) ($stats['numberOfDocuments'] ?? -1);

        if ($actualCount !== $expectedCount) {
            throw new RuntimeException("Index [{$indexName}] contains {$actualCount} documents; expected {$expectedCount}.");
        }

        foreach ($samples as $eventId => $expected) {
            $actual = $this->gateway->document($indexName, $eventId, EventSearchDocument::DISPLAYED_ATTRIBUTES);

            if ($actual != $expected) {
                throw new RuntimeException("Index [{$indexName}] failed representative document validation for event [{$eventId}].");
            }
        }
    }

    /** @param array<string, mixed> $task */
    private function requireSucceededTask(string $operation, array $task): void
    {
        if (($task['status'] ?? null) === 'succeeded') {
            return;
        }

        $taskUid = $task['uid'] ?? $task['taskUid'] ?? 'unknown';
        $error = $task['error']['message'] ?? $task['status'] ?? 'unknown failure';

        throw new RuntimeException("Meilisearch failed to {$operation} (task {$taskUid}): {$error}");
    }

    /** @param list<int> $taskUids */
    private function requireAllTasksSucceeded(array $taskUids): void
    {
        $tasks = $this->gateway->tasks($taskUids);

        foreach ($taskUids as $taskUid) {
            if (! isset($tasks[$taskUid])) {
                throw new RuntimeException("Meilisearch task [{$taskUid}] was not returned during bulk verification.");
            }

            $this->requireSucceededTask('index one document batch', $tasks[$taskUid]);
        }
    }

    /** @return array{status: string, task_uid?: int, message?: string} */
    private function cleanupDisplacedIndex(string $indexName, int $timeoutMs): array
    {
        try {
            $taskUid = $this->gateway->deleteIndex($indexName);
            $task = $this->gateway->waitForTask($taskUid, $timeoutMs);
            $this->requireSucceededTask('delete displaced event index', $task);

            return ['status' => 'deleted', 'task_uid' => $taskUid];
        } catch (Throwable $exception) {
            Log::warning('Meilisearch displaced event index cleanup failed after a verified swap.', [
                'index' => $indexName,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return ['status' => 'warning', 'message' => $exception->getMessage()];
        }
    }

    private function temporaryIndexName(string $logicalIndex): string
    {
        return sprintf(
            '%s__build__%s_%s',
            $logicalIndex,
            Date::now('UTC')->format('Ymd_His'),
            Str::lower(Str::random(8)),
        );
    }
}
