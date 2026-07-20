<?php

namespace App\Console\Commands;

use App\Services\Attendance\ReminderDispatcher;
use Illuminate\Console\Command;

class DispatchAttendanceReminders extends Command
{
    protected $signature = 'attendance:dispatch-reminders {--limit=200 : Maximum reminders to claim}';

    protected $description = 'Claim and queue due event attendance reminders';

    public function handle(ReminderDispatcher $dispatcher): int
    {
        $count = $dispatcher->dispatchDue((int) $this->option('limit'));
        $this->info("Queued {$count} reminder(s).");

        return self::SUCCESS;
    }
}
