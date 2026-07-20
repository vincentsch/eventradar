<?php

namespace App\Models;

use App\Domain\Attendance\AttendanceIntent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventAttendance extends Model
{
    use HasUuids;

    protected $fillable = ['event_id', 'user_id', 'intent', 'revision'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'intent' => AttendanceIntent::class,
            'revision' => 'integer',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<AttendanceDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(AttendanceDelivery::class, 'attendance_id');
    }
}
