<?php

namespace App\Domain\Events;

enum ImageRole: string
{
    case Cover = 'cover';
    case Detail = 'detail';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
