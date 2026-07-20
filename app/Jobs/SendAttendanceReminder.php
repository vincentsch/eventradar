<?php

namespace App\Jobs;

use App\Domain\Attendance\DeliveryStatus;
use App\Domain\Events\EventStatus;
use App\Mail\EventReminderMail;
use App\Models\AttendanceDelivery;
use App\Services\Attendance\ReminderDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends only the delivery claimed by the dispatcher. The claim token and
 * attendance revision make delayed or superseded jobs harmless.
 *
 * SMTP delivery and the ledger update cannot be one atomic transaction. A
 * worker crash between them can repeat a message, which is preferable to
 * marking an unsent reminder as delivered.
 */
class SendAttendanceReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public function __construct(
        public readonly int $deliveryId,
        public readonly string $claimToken,
    ) {}

    public function handle(): void
    {
        $delivery = AttendanceDelivery::query()
            ->with([
                'attendance.user:id,name,email',
                'attendance.event:id,title,status,starts_at,ends_at,timezone,venue_name,formatted_address,locality,region,country',
            ])
            ->whereKey($this->deliveryId)
            ->where('claim_token', $this->claimToken)
            ->where('status', DeliveryStatus::Processing->value)
            ->first();

        if ($delivery === null) {
            return;
        }

        $attendance = $delivery->attendance;
        $event = $attendance->event;
        $invalid = $attendance->cancelled_at !== null
            || $attendance->revision !== $delivery->attendance_revision
            || ! in_array($event->status->value, EventStatus::publicValues(), true)
            || $event->ends_at->isPast()
            || $delivery->due_at->lt(now('UTC')->subHours(ReminderDispatcher::GRACE_HOURS));

        if ($invalid) {
            $this->finish(DeliveryStatus::Skipped, 'Attendance or event is no longer active.');

            return;
        }

        Mail::to($attendance->user->email, $attendance->user->name)
            ->send(new EventReminderMail($attendance, $delivery->kind));

        $this->finish(DeliveryStatus::Sent);
    }

    public function failed(?Throwable $exception): void
    {
        AttendanceDelivery::query()
            ->whereKey($this->deliveryId)
            ->where('claim_token', $this->claimToken)
            ->update([
                'status' => DeliveryStatus::Failed->value,
                'failed_at' => now('UTC'),
                'claim_token' => null,
                'claimed_at' => null,
                'last_error' => Str::limit($exception?->getMessage() ?? 'Reminder job failed.', 500, ''),
                'updated_at' => now('UTC'),
            ]);
    }

    private function finish(DeliveryStatus $status, ?string $reason = null): void
    {
        AttendanceDelivery::query()
            ->whereKey($this->deliveryId)
            ->where('claim_token', $this->claimToken)
            ->update([
                'status' => $status->value,
                'sent_at' => $status === DeliveryStatus::Sent ? now('UTC') : null,
                'skipped_at' => $status === DeliveryStatus::Skipped ? now('UTC') : null,
                'claim_token' => null,
                'claimed_at' => null,
                'last_error' => $reason,
                'updated_at' => now('UTC'),
            ]);
    }
}
