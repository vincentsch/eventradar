<?php

namespace App\Jobs;

use App\Models\Event;
use App\Services\Attendance\AttendanceManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RescheduleEventAttendances implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public int $uniqueFor = 900;

    public function __construct(public readonly string $eventId) {}

    public function uniqueId(): string
    {
        return $this->eventId;
    }

    public function handle(AttendanceManager $manager): void
    {
        $event = Event::query()->find($this->eventId, ['id', 'status', 'starts_at', 'ends_at']);

        if ($event !== null) {
            $manager->rescheduleForEvent($event);
        }
    }
}
