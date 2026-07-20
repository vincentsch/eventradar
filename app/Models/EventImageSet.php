<?php

namespace App\Models;

use App\Domain\Events\EventType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $key
 * @property EventType $category
 * @property-read Collection<int, EventImage> $images
 */
class EventImageSet extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $fillable = ['key', 'category'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['category' => EventType::class];
    }

    /** @return HasMany<EventImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(EventImage::class, 'image_set_key', 'key');
    }
}
