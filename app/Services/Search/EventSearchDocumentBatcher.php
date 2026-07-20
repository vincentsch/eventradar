<?php

namespace App\Services\Search;

use JsonException;
use LengthException;

final class EventSearchDocumentBatcher
{
    /** @var list<string> */
    private array $encodedDocuments = [];

    private int $encodedBytes = 2;

    public function __construct(
        private readonly int $documentLimit,
        private readonly int $byteLimit,
    ) {
        if ($documentLimit < 1 || $byteLimit < 3) {
            throw new LengthException('Meilisearch batch limits must be positive.');
        }
    }

    /**
     * Add one document and return the preceding full batch when a limit is crossed.
     *
     * @param  array<string, mixed>  $document
     * @return array{json: string, count: int, bytes: int}|null
     *
     * @throws JsonException
     */
    public function add(array $document): ?array
    {
        $encoded = json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $standaloneBytes = strlen($encoded) + 2;

        if ($standaloneBytes > $this->byteLimit) {
            throw new LengthException("One event search document is {$standaloneBytes} bytes; the batch limit is {$this->byteLimit} bytes.");
        }

        $separatorBytes = $this->encodedDocuments === [] ? 0 : 1;
        $wouldExceed = count($this->encodedDocuments) >= $this->documentLimit
            || $this->encodedBytes + $separatorBytes + strlen($encoded) > $this->byteLimit;

        $fullBatch = $wouldExceed ? $this->flush() : null;
        $this->encodedBytes += ($this->encodedDocuments === [] ? 0 : 1) + strlen($encoded);
        $this->encodedDocuments[] = $encoded;

        return $fullBatch;
    }

    /** @return array{json: string, count: int, bytes: int}|null */
    public function flush(): ?array
    {
        if ($this->encodedDocuments === []) {
            return null;
        }

        $json = '['.implode(',', $this->encodedDocuments).']';
        $batch = [
            'json' => $json,
            'count' => count($this->encodedDocuments),
            'bytes' => strlen($json),
        ];

        $this->encodedDocuments = [];
        $this->encodedBytes = 2;

        return $batch;
    }
}
