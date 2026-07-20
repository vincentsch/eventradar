<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
