<?php

namespace App\Models;

use App\Domain\Events\ImageRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $image_set_key
 * @property ImageRole $role
 * @property string $path
 * @property int $width
 * @property int $height
 * @property string $sha256
 * @property string $alt
 */
class EventImage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'image_set_key',
        'role',
        'path',
        'width',
        'height',
        'sha256',
        'alt',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'role' => ImageRole::class,
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    /** @return BelongsTo<EventImageSet, $this> */
    public function imageSet(): BelongsTo
    {
        return $this->belongsTo(EventImageSet::class, 'image_set_key', 'key');
    }
}
