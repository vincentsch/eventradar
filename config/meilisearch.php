<?php

return [
    'host' => env('MEILISEARCH_HOST', 'http://127.0.0.1:7700'),
    'key' => env('MEILISEARCH_KEY'),
    'event_index' => env('MEILISEARCH_EVENT_INDEX', 'events_v1'),

    'http_timeout_seconds' => (float) env('MEILISEARCH_TIMEOUT', 5),
    'build_timeout_ms' => (int) env('MEILISEARCH_BUILD_TIMEOUT_MS', 1_800_000),
    'reconcile_timeout_ms' => (int) env('MEILISEARCH_RECONCILE_TIMEOUT_MS', 30_000),
    'task_poll_interval_ms' => (int) env('MEILISEARCH_TASK_POLL_INTERVAL_MS', 200),

    'database_chunk_size' => (int) env('MEILISEARCH_DATABASE_CHUNK_SIZE', 2_000),
    'batch_document_limit' => (int) env('MEILISEARCH_BATCH_DOCUMENT_LIMIT', 10_000),
    'batch_byte_limit' => (int) env('MEILISEARCH_BATCH_BYTE_LIMIT', 8 * 1024 * 1024),
    'task_query_chunk_size' => (int) env('MEILISEARCH_TASK_QUERY_CHUNK_SIZE', 100),
    'pagination_max_total_hits' => (int) env('MEILISEARCH_MAX_TOTAL_HITS', 1_000),
];
