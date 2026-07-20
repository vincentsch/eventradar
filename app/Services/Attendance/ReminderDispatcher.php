<?php

namespace App\Services\Attendance;

use App\Domain\Attendance\DeliveryKind;
use App\Domain\Attendance\DeliveryStatus;
use App\Jobs\SendAttendanceReminder;
use App\Models\AttendanceDelivery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReminderDispatcher
{
    public function dispatchDue(int $limit = 200): int
    {
        $limit = max(1, min($limit, 1000));

        /** @var list<array{id: int, token: string}> $claims */
        $claims = DB::transaction(function () use ($limit): array {
            $query = AttendanceDelivery::query()
                ->whereIn('kind', [DeliveryKind::ThreeDays->value, DeliveryKind::OneDay->value])
                ->where('due_at', '<=', now('UTC'))
                ->where('attempt_count', '<', 3)
                ->where(function (Builder $query): void {
                    $query->where('status', DeliveryStatus::Pending->value)
                        ->orWhere(function (Builder $query): void {
                            $query->where('status', DeliveryStatus::Failed->value)
                                ->where('failed_at', '<=', now('UTC')->subMinutes(15));
                        })
                        ->orWhere(function (Builder $query): void {
                            $query->where('status', DeliveryStatus::Processing->value)
                                ->where('claimed_at', '<=', now('UTC')->subMinutes(15));
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

            return $query->get()->map(function (AttendanceDelivery $delivery): array {
                $token = (string) Str::uuid();

                $delivery->forceFill([
                    'status' => DeliveryStatus::Processing,
                    'claim_token' => $token,
                    'claimed_at' => now('UTC'),
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
