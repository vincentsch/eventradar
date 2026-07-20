<?php

namespace App\Domain\Events;

enum EventType: string
{
    case Concert = 'concert';
    case Conference = 'conference';
    case Meetup = 'meetup';
    case Workshop = 'workshop';
    case Festival = 'festival';
    case Sports = 'sports';
    case Networking = 'networking';
    case Exhibition = 'exhibition';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
