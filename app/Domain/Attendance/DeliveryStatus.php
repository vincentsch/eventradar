<?php

namespace App\Domain\Attendance;

enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Sent = 'sent';
    case Skipped = 'skipped';
    case Failed = 'failed';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
