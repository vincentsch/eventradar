<?php

namespace App\Services\Attendance;

use App\Domain\Attendance\AttendanceIntent;
use App\Domain\Attendance\DeliveryKind;
use App\Domain\Attendance\DeliveryStatus;
use App\Domain\Events\EventStatus;
use App\Jobs\SendAttendanceConfirmation;
use App\Models\AttendanceDelivery;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceManager
{
    public function register(User $user, Event $event, AttendanceIntent $intent): EventAttendance
    {
        $this->ensureEventCanAcceptAttendance($event);

        /** @var array{attendance: EventAttendance, confirmation_required: bool} $result */
        $result = DB::transaction(function () use ($user, $event, $intent): array {
            $attendance = EventAttendance::query()->createOrFirst(
                ['event_id' => $event->id, 'user_id' => $user->id],
                ['intent' => $intent, 'revision' => 1],
            );
            $created = $attendance->wasRecentlyCreated;

            $attendance = EventAttendance::query()
                ->whereKey($attendance->id)
                ->lockForUpdate()
                ->firstOrFail();
            $reactivated = $attendance->cancelled_at !== null;

            if ($reactivated) {
                $attendance->revision++;
                $attendance->cancelled_at = null;
            }

            $attendance->intent = $intent;
            $attendance->save();

            if ($created || $reactivated) {
                $this->createDeliveries($attendance, $event);
            }

            return [
                'attendance' => $attendance,
                'confirmation_required' => $created || $reactivated,
            ];
        }, 3);

        if ($result['confirmation_required']) {
            $result['attendance']->wasRecentlyCreated = true;
            SendAttendanceConfirmation::dispatch(
                $result['attendance']->id,
                $result['attendance']->revision,
            )->onQueue('mail')->afterCommit();
        }

        return $result['attendance'];
    }

    public function cancel(EventAttendance $attendance): bool
    {
        return DB::transaction(function () use ($attendance): bool {
            $locked = EventAttendance::query()->whereKey($attendance->id)->lockForUpdate()->first();

            if ($locked === null || $locked->cancelled_at !== null) {
                return false;
            }

            $locked->cancelled_at = now('UTC');
            $locked->revision++;
            $locked->save();

            AttendanceDelivery::query()
                ->where('attendance_id', $locked->id)
                ->whereIn('status', [
                    DeliveryStatus::Pending->value,
                    DeliveryStatus::Processing->value,
                    DeliveryStatus::Failed->value,
                ])
                ->update([
                    'status' => DeliveryStatus::Skipped->value,
                    'skipped_at' => now('UTC'),
                    'claim_token' => null,
                    'claimed_at' => null,
                    'last_error' => 'Attendance cancelled.',
                    'updated_at' => now('UTC'),
                ]);

            return true;
        }, 3);
    }

    private function ensureEventCanAcceptAttendance(Event $event): void
    {
        if (! in_array($event->status->value, EventStatus::publicValues(), true) || $event->ends_at->isPast()) {
            throw ValidationException::withMessages([
                'intent' => 'This event is no longer accepting attendance updates.',
            ]);
        }
    }

    private function createDeliveries(EventAttendance $attendance, Event $event): void
    {
        $now = now('UTC');
        $deliveries = [
            DeliveryKind::Confirmation->value => $now,
            DeliveryKind::ThreeDays->value => $event->starts_at->subDays(3),
            DeliveryKind::OneDay->value => $event->starts_at->subDay(),
        ];

        foreach ($deliveries as $kind => $dueAt) {
            $isMissedReminder = $kind !== DeliveryKind::Confirmation->value && $dueAt->lte($now);

            AttendanceDelivery::query()->create([
                'attendance_id' => $attendance->id,
                'attendance_revision' => $attendance->revision,
                'kind' => $kind,
                'status' => $isMissedReminder
                    ? DeliveryStatus::Skipped->value
                    : DeliveryStatus::Pending->value,
                'due_at' => $dueAt,
                'skipped_at' => $isMissedReminder ? $now : null,
                'last_error' => $isMissedReminder ? 'Reminder horizon passed before registration.' : null,
            ]);
        }
    }
}
