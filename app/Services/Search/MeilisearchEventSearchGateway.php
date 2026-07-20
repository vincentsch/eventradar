<?php

namespace App\Services\Search;

use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;
use Meilisearch\Exceptions\ApiException;
use RuntimeException;

final class MeilisearchEventSearchGateway implements EventSearchGateway
{
    public function __construct(private readonly Client $client) {}

    public function indexExists(string $indexName): bool
    {
        try {
            $this->client->getIndex($indexName);

            return true;
        } catch (ApiException $exception) {
            if ($exception->errorCode === 'index_not_found') {
                return false;
            }

            throw $exception;
        }
    }

    public function createIndex(string $indexName, string $primaryKey): int
    {
        return $this->taskUid($this->client->createIndex($indexName, ['primaryKey' => $primaryKey]));
    }

    public function updateSettings(string $indexName, array $settings): int
    {
        return $this->taskUid($this->client->index($indexName)->updateSettings($settings));
    }

    public function addDocumentsJson(string $indexName, string $documents, string $primaryKey): int
    {
        return $this->taskUid($this->client->index($indexName)->addDocumentsJson($documents, $primaryKey));
    }

    public function deleteDocuments(string $indexName, array $documentIds): int
    {
        return $this->taskUid($this->client->index($indexName)->deleteDocuments($documentIds));
    }

    public function waitForTask(int $taskUid, int $timeoutMs): array
    {
        return $this->client->waitForTask(
            $taskUid,
            $timeoutMs,
            (int) config('meilisearch.task_poll_interval_ms', 200),
        );
    }

    public function tasks(array $taskUids): array
    {
        $tasks = [];
        $chunkSize = max(1, (int) config('meilisearch.task_query_chunk_size', 100));

        foreach (array_chunk($taskUids, $chunkSize) as $uids) {
            $query = (new TasksQuery)
                ->setUids($uids)
                ->setLimit(count($uids));

            foreach ($this->client->getTasks($query)->getResults() as $task) {
                if (isset($task['uid'])) {
                    $tasks[(int) $task['uid']] = $task;
                }
            }
        }

        return $tasks;
    }

    public function stats(string $indexName): array
    {
        return $this->client->index($indexName)->stats();
    }

    public function document(string $indexName, string $documentId, array $fields): array
    {
        /** @var array<string, mixed> $document */
        $document = $this->client->index($indexName)->getDocument($documentId, $fields);

        return $document;
    }

    public function swapIndexes(string $firstIndex, string $secondIndex): int
    {
        return $this->taskUid($this->client->swapIndexes([[$firstIndex, $secondIndex]]));
    }

    public function deleteIndex(string $indexName): int
    {
        return $this->taskUid($this->client->deleteIndex($indexName));
    }

    /** @param array<string, mixed> $task */
    private function taskUid(array $task): int
    {
        if (! isset($task['taskUid'])) {
            throw new RuntimeException('Meilisearch accepted an operation without returning a task UID.');
        }

        return (int) $task['taskUid'];
    }
}
