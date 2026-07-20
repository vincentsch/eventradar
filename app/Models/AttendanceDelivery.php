<?php

namespace App\Models;

use App\Domain\Attendance\DeliveryKind;
use App\Domain\Attendance\DeliveryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
