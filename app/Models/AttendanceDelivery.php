<?php

namespace App\Models;

use App\Domain\Attendance\DeliveryKind;
use App\Domain\Attendance\DeliveryStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $attendance_id
 * @property int $attendance_revision
 * @property DeliveryKind $kind
 * @property DeliveryStatus $status
 * @property CarbonImmutable $due_at
 * @property string|null $claim_token
 * @property CarbonImmutable|null $claimed_at
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $skipped_at
 * @property CarbonImmutable|null $failed_at
 * @property int $attempt_count
 * @property string|null $last_error
 * @property-read EventAttendance $attendance
 */
class AttendanceDelivery extends Model
{
    protected $fillable = [
        'attendance_id',
        'attendance_revision',
        'kind',
        'status',
        'due_at',
        'claim_token',
        'claimed_at',
        'sent_at',
        'skipped_at',
        'failed_at',
        'attempt_count',
        'last_error',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'attendance_revision' => 'integer',
            'kind' => DeliveryKind::class,
            'status' => DeliveryStatus::class,
            'due_at' => 'immutable_datetime',
            'claimed_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'skipped_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'attempt_count' => 'integer',
        ];
    }

    /** @return BelongsTo<EventAttendance, $this> */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(EventAttendance::class, 'attendance_id');
    }
}
