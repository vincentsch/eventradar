<?php

namespace App\Domain\Attendance;

enum DeliveryKind: string
{
    case Confirmation = 'confirmation';
    case ThreeDays = 'three_days';
    case OneDay = 'one_day';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
