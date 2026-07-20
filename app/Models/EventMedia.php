<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $event_id
 * @property string $disk
 * @property string $path
 * @property string $card_path
 * @property int $position
 * @property int $width
 * @property int $height
 * @property int $card_width
 * @property int $card_height
 * @property string $mime_type
 * @property int $byte_size
 * @property string $sha256
 * @property string $alt
 */
class EventMedia extends Model
{
    protected $table = 'event_media';

    protected $fillable = [
        'disk',
        'path',
        'card_path',
        'position',
        'width',
        'height',
        'card_width',
        'card_height',
        'mime_type',
        'byte_size',
        'sha256',
        'alt',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'card_width' => 'integer',
            'card_height' => 'integer',
            'byte_size' => 'integer',
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function url(): string
    {
        return '/storage/'.ltrim($this->path, '/');
    }

    public function cardUrl(): string
    {
        return '/storage/'.ltrim($this->card_path, '/');
    }
}
