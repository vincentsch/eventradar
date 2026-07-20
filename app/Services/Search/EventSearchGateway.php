<?php

namespace App\Services\Search;

interface EventSearchGateway
{
    public function indexExists(string $indexName): bool;

    public function createIndex(string $indexName, string $primaryKey): int;

    /** @param array<string, mixed> $settings */
    public function updateSettings(string $indexName, array $settings): int;

    public function addDocumentsJson(string $indexName, string $documents, string $primaryKey): int;

    /** @param list<string> $documentIds */
    public function deleteDocuments(string $indexName, array $documentIds): int;

    /** @return array<string, mixed> */
    public function waitForTask(int $taskUid, int $timeoutMs): array;

    /**
     * @param  list<int>  $taskUids
     * @return array<int, array<string, mixed>> Tasks keyed by task UID.
     */
    public function tasks(array $taskUids): array;

    /** @return array<string, mixed> */
    public function stats(string $indexName): array;

    /**
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    public function document(string $indexName, string $documentId, array $fields): array;

    public function swapIndexes(string $firstIndex, string $secondIndex): int;

    public function deleteIndex(string $indexName): int;
}
