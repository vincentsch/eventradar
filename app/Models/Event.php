<?php

namespace App\Models;

use App\Domain\Events\EventStatus;
use App\Domain\Events\EventType;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * The authoritative normalized event. Payload deliberately remains an uncast provenance string.
 *
 * @property EventStatus $status
 * @property EventType $type
 */
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'title',
        'description',
        'organizer_name',
        'venue_name',
        'formatted_address',
        'address_line_1',
        'starts_at',
        'ends_at',
        'timezone',
        'starts_on_local',
        'location_key',
        'locality',
        'region',
        'postal_code',
        'country',
        'country_code',
        'latitude',
        'longitude',
        'image_set_key',
        'status',
        'type',
        'tags',
        'minimum_price',
        'currency_code',
        'capacity',
    ];

    protected $hidden = ['payload', 'user_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'starts_on_local' => 'immutable_date:Y-m-d',
            'status' => EventStatus::class,
            'type' => EventType::class,
            'tags' => 'array',
            'latitude' => 'float',
            'longitude' => 'float',
            'minimum_price' => 'decimal:2',
            'capacity' => 'integer',
        ];
    }

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<EventImageSet, $this> */
    public function imageSet(): BelongsTo
    {
        return $this->belongsTo(EventImageSet::class, 'image_set_key', 'key');
    }

    /** @return HasMany<EventAttendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(EventAttendance::class);
    }

    /** @return HasMany<EventMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(EventMedia::class)->orderBy('position');
    }
}
