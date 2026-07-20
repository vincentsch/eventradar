<?php

namespace App\Console\Commands;

use App\Services\Search\EventSearchIndexer;
use Illuminate\Console\Command;

class RebuildEventSearchIndex extends Command
{
    /** @var string */
    protected $signature = 'events:search-index {--json : Emit machine-readable metrics}';

    /** @var string */
    protected $description = 'Build and atomically promote the public event search index';

    public function handle(EventSearchIndexer $indexer): int
    {
        $result = $indexer->rebuild();

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info("Promoted [{$result['logical_index']}] with {$result['documents_submitted']} documents.");
        $this->table(['Metric', 'Value'], [
            ['Eligibility instant', $result['eligibility_instant']],
            ['Source rows', number_format((int) $result['source_count'])],
            ['Batches', number_format((int) $result['batches_submitted'])],
            ['Document tasks', number_format((int) $result['document_task_count'])],
            ['Payload', $this->formatBytes((int) $result['payload_bytes'])],
            ['Duration', number_format((float) $result['duration_seconds'], 3).' s'],
            ['Throughput', $this->throughput($result).' documents/s'],
            ['Peak PHP memory', $this->formatBytes((int) $result['peak_memory_bytes'])],
            ['Cleanup', $result['cleanup']['status']],
        ]);

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $result */
    private function throughput(array $result): string
    {
        $duration = (float) $result['duration_seconds'];

        return $duration <= 0
            ? 'n/a'
            : number_format((int) $result['documents_submitted'] / $duration, 1);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return "{$bytes} B";
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KiB';
        }

        return number_format($bytes / 1024 / 1024, 1).' MiB';
    }
}
