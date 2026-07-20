<?php

namespace Tests\Support;

use App\Services\Search\EventSearchGateway;
use Closure;
use RuntimeException;

final class FakeEventSearchGateway implements EventSearchGateway
{
    /** @var array<string, array{documents: array<string, array<string, mixed>>, settings: array<string, mixed>}> */
    public array $indexes = [];

    /** @var array<int, array<string, mixed>> */
    public array $taskRecords = [];

    /** @var list<array{uid: int, timeout: int}> */
    public array $waits = [];

    /** @var list<list<int>> */
    public array $bulkTaskLookups = [];

    /** @var list<string> */
    public array $operations = [];

    public ?int $failDocumentTaskNumber = null;

    public bool $failSwap = false;

    public bool $failCleanup = false;

    public bool $corruptStats = false;

    public bool $corruptDocumentRead = false;

    public ?int $corruptDocumentReadNumber = null;

    public ?Closure $afterDocumentsAdded = null;

    private int $nextTaskUid = 1;

    private int $documentTaskNumber = 0;

    private int $documentReadNumber = 0;

    public function indexExists(string $indexName): bool
    {
        return isset($this->indexes[$indexName]);
    }

    public function createIndex(string $indexName, string $primaryKey): int
    {
        if ($this->indexExists($indexName)) {
            throw new RuntimeException("Index [{$indexName}] already exists.");
        }

        $this->operations[] = "create:{$indexName}";
        $this->indexes[$indexName] = ['documents' => [], 'settings' => ['primaryKey' => $primaryKey]];

        return $this->task('succeeded');
    }

    public function updateSettings(string $indexName, array $settings): int
    {
        $this->operations[] = "settings:{$indexName}";
        $this->indexes[$indexName]['settings'] = $settings;

        return $this->task('succeeded');
    }

    public function addDocumentsJson(string $indexName, string $documents, string $primaryKey): int
    {
        $this->documentTaskNumber++;
        $this->operations[] = "documents:{$indexName}:{$this->documentTaskNumber}";
        $decoded = json_decode($documents, true, flags: JSON_THROW_ON_ERROR);
        $failed = $this->failDocumentTaskNumber === $this->documentTaskNumber;

        if (! $failed) {
            foreach ($decoded as $document) {
                $this->indexes[$indexName]['documents'][(string) $document[$primaryKey]] = $document;
            }
        }

        $taskUid = $this->task($failed ? 'failed' : 'succeeded', $failed ? 'simulated document failure' : null);

        if ($this->afterDocumentsAdded !== null) {
            ($this->afterDocumentsAdded)();
        }

        return $taskUid;
    }

    public function deleteDocuments(string $indexName, array $documentIds): int
    {
        $this->operations[] = "remove:{$indexName}";
        foreach ($documentIds as $documentId) {
            unset($this->indexes[$indexName]['documents'][$documentId]);
        }

        return $this->task('succeeded');
    }

    public function waitForTask(int $taskUid, int $timeoutMs): array
    {
        $this->waits[] = ['uid' => $taskUid, 'timeout' => $timeoutMs];

        return $this->taskRecords[$taskUid];
    }

    public function tasks(array $taskUids): array
    {
        $this->bulkTaskLookups[] = $taskUids;

        return array_intersect_key($this->taskRecords, array_flip($taskUids));
    }

    public function stats(string $indexName): array
    {
        $count = count($this->indexes[$indexName]['documents']);

        return ['numberOfDocuments' => $this->corruptStats ? $count + 1 : $count];
    }

    public function document(string $indexName, string $documentId, array $fields): array
    {
        $this->documentReadNumber++;
        $document = $this->indexes[$indexName]['documents'][$documentId];
        $document = array_intersect_key($document, array_flip($fields));

        if ($this->corruptDocumentRead || $this->corruptDocumentReadNumber === $this->documentReadNumber) {
            $document['title'] = 'Corrupted title';
        }

        return $document;
    }

    public function swapIndexes(string $firstIndex, string $secondIndex): int
    {
        $this->operations[] = "swap:{$firstIndex}:{$secondIndex}";

        if ($this->failSwap) {
            return $this->task('failed', 'simulated swap failure');
        }

        [$this->indexes[$firstIndex], $this->indexes[$secondIndex]] = [
            $this->indexes[$secondIndex],
            $this->indexes[$firstIndex],
        ];

        return $this->task('succeeded');
    }

    public function deleteIndex(string $indexName): int
    {
        $this->operations[] = "delete:{$indexName}";

        if ($this->failCleanup) {
            return $this->task('failed', 'simulated cleanup failure');
        }

        unset($this->indexes[$indexName]);

        return $this->task('succeeded');
    }

    private function task(string $status, ?string $error = null): int
    {
        $taskUid = $this->nextTaskUid++;
        $record = ['uid' => $taskUid, 'status' => $status];

        if ($error !== null) {
            $record['error'] = ['message' => $error];
        }

        $this->taskRecords[$taskUid] = $record;

        return $taskUid;
    }
}
