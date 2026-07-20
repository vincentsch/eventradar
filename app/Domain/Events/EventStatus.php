<?php

namespace App\Domain\Events;

enum EventStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Cancelled = 'cancelled';
    case SoldOut = 'sold_out';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return list<string> */
    public static function publicValues(): array
    {
        return [self::Published->value, self::SoldOut->value];
    }
}
