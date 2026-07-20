<?php

namespace App\Jobs;

use App\Domain\Attendance\DeliveryKind;
use App\Domain\Attendance\DeliveryStatus;
use App\Mail\AttendanceConfirmationMail;
use App\Models\AttendanceDelivery;
use App\Models\EventAttendance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/** Delivers the revision-bound confirmation recorded in the delivery ledger. */
class SendAttendanceConfirmation implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public int $uniqueFor = 900;

    public function __construct(
        public readonly string $attendanceId,
        public readonly int $attendanceRevision,
    ) {}

    public function uniqueId(): string
    {
        return $this->attendanceId.':'.$this->attendanceRevision;
    }

    public function handle(): void
    {
        $claimToken = (string) Str::uuid();

        $claimed = DB::transaction(function () use ($claimToken): bool {
            $delivery = AttendanceDelivery::query()
                ->where('attendance_id', $this->attendanceId)
                ->where('attendance_revision', $this->attendanceRevision)
                ->where('kind', DeliveryKind::Confirmation->value)
                ->lockForUpdate()
                ->first();

            if ($delivery === null || in_array($delivery->status, [
                DeliveryStatus::Sent,
                DeliveryStatus::Skipped,
            ], true)) {
                return false;
            }

            $delivery->forceFill([
                'status' => DeliveryStatus::Processing,
                'claim_token' => $claimToken,
                'claimed_at' => now('UTC'),
                'failed_at' => null,
                'last_error' => null,
                'attempt_count' => $delivery->attempt_count + 1,
            ])->save();

            return true;
        }, 3);

        if (! $claimed) {
            return;
        }

        try {
            $attendance = EventAttendance::query()
                ->with(['user:id,name,email', 'event:id,title,starts_at,ends_at,timezone,venue_name,formatted_address,locality,region,country'])
                ->find($this->attendanceId);

            if ($attendance === null
                || $attendance->revision !== $this->attendanceRevision
                || $attendance->cancelled_at !== null
                || $attendance->event->ends_at->isPast()) {
                $this->finish($claimToken, DeliveryStatus::Skipped, 'Attendance is no longer active.');

                return;
            }

            Mail::to($attendance->user->email, $attendance->user->name)
                ->send(new AttendanceConfirmationMail($attendance));

            $this->finish($claimToken, DeliveryStatus::Sent);
        } catch (Throwable $exception) {
            AttendanceDelivery::query()
                ->where('claim_token', $claimToken)
                ->update([
                    'status' => DeliveryStatus::Failed->value,
                    'failed_at' => now('UTC'),
                    'claim_token' => null,
                    'claimed_at' => null,
                    'last_error' => Str::limit($exception->getMessage(), 500, ''),
                    'updated_at' => now('UTC'),
                ]);

            throw $exception;
        }
    }

    private function finish(
        string $claimToken,
        DeliveryStatus $status,
        ?string $reason = null,
    ): void {
        AttendanceDelivery::query()
            ->where('claim_token', $claimToken)
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
