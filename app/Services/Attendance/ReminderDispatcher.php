<?php

namespace App\Services\Attendance;

use App\Domain\Attendance\DeliveryKind;
use App\Domain\Attendance\DeliveryStatus;
use App\Jobs\SendAttendanceReminder;
use App\Models\AttendanceDelivery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReminderDispatcher
{
    public const GRACE_HOURS = 6;

    public function dispatchDue(int $limit = 200): int
    {
        $limit = max(1, min($limit, 1000));
        $now = Date::now('UTC')->toImmutable();
        $staleBefore = $now->subHours(self::GRACE_HOURS);

        AttendanceDelivery::query()
            ->whereIn('kind', [DeliveryKind::ThreeDays->value, DeliveryKind::OneDay->value])
            ->where('due_at', '<', $staleBefore)
            ->whereIn('status', [DeliveryStatus::Pending->value, DeliveryStatus::Failed->value])
            ->update([
                'status' => DeliveryStatus::Skipped->value,
                'skipped_at' => $now,
                'claim_token' => null,
                'claimed_at' => null,
                'last_error' => 'Reminder delivery window passed.',
                'updated_at' => $now,
            ]);

        /** @var list<array{id: int, token: string}> $claims */
        $claims = DB::transaction(function () use ($limit, $now, $staleBefore): array {
            $query = AttendanceDelivery::query()
                ->whereIn('kind', [DeliveryKind::ThreeDays->value, DeliveryKind::OneDay->value])
                ->whereBetween('due_at', [$staleBefore, $now])
                ->where('attempt_count', '<', 3)
                ->where(function (Builder $query) use ($now): void {
                    $query->where('status', DeliveryStatus::Pending->value)
                        ->orWhere(function (Builder $query) use ($now): void {
                            $query->where('status', DeliveryStatus::Failed->value)
                                ->where('failed_at', '<=', $now->subMinutes(15));
                        })
                        ->orWhere(function (Builder $query) use ($now): void {
                            $query->where('status', DeliveryStatus::Processing->value)
                                ->where('claimed_at', '<=', $now->subMinutes(15));
                        });
                })
                ->orderBy('due_at')
                ->orderBy('id')
                ->limit($limit);

            if (DB::connection()->getDriverName() === 'mysql') {
                $query->lock('FOR UPDATE SKIP LOCKED');
            } else {
                $query->lockForUpdate();
            }

            return $query->get()->map(function (AttendanceDelivery $delivery) use ($now): array {
                $token = (string) Str::uuid();

                $delivery->forceFill([
                    'status' => DeliveryStatus::Processing,
                    'claim_token' => $token,
                    'claimed_at' => $now,
                    'attempt_count' => $delivery->attempt_count + 1,
                    'failed_at' => null,
                    'last_error' => null,
                ])->save();

                return ['id' => $delivery->id, 'token' => $token];
            })->all();
        }, 3);

        foreach ($claims as $claim) {
            SendAttendanceReminder::dispatch($claim['id'], $claim['token'])->onQueue('mail');
        }

        return count($claims);
    }
}
