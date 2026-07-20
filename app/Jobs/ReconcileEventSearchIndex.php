<?php

namespace App\Jobs;

use App\Services\Search\EventSearchIndexer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReconcileEventSearchIndex implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [5, 30, 120, 300];

    public int $uniqueFor = 600;

    public function __construct(public readonly string $eventId) {}

    public function uniqueId(): string
    {
        return $this->eventId;
    }

    public function handle(EventSearchIndexer $indexer): void
    {
        $indexer->reconcile($this->eventId);
    }
}
