<?php

namespace App\Domain\Attendance;

enum AttendanceIntent: string
{
    case Interested = 'interested';
    case Going = 'going';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
