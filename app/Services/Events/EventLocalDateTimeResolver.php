<?php

namespace App\Services\Events;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Validation\ValidationException;

final class EventLocalDateTimeResolver
{
    public function resolve(
        string $local,
        string $timezone,
        ?string $requestedOffset,
        string $field,
        ?string $offsetField = null,
    ): CarbonImmutable {
        $zone = new DateTimeZone($timezone);
        $wallClock = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $local, new DateTimeZone('UTC'));

        if ($wallClock === false || $wallClock->format('Y-m-d\TH:i') !== $local) {
            throw ValidationException::withMessages([$field => 'Enter a valid local date and time.']);
        }

        $wallTimestamp = $wallClock->getTimestamp();
        $offsets = [];
        foreach ($zone->getTransitions($wallTimestamp - 172800, $wallTimestamp + 172800) ?: [] as $transition) {
            $offsets[(int) $transition['offset']] = true;
        }

        $candidates = [];
        foreach (array_keys($offsets) as $offset) {
            $candidate = CarbonImmutable::createFromTimestampUTC($wallTimestamp - $offset)->setTimezone($zone);
            if ($candidate->format('Y-m-d\TH:i') === $local) {
                $candidates[$candidate->format('P')] = $candidate->utc();
            }
        }

        if ($candidates === []) {
            throw ValidationException::withMessages([
                $field => 'This local time does not exist because the clock moves forward. Choose another time.',
            ]);
        }

        if ($requestedOffset !== null && isset($candidates[$requestedOffset])) {
            return $candidates[$requestedOffset];
        }

        if (count($candidates) > 1) {
            throw ValidationException::withMessages([
                ($offsetField ?? $field.'_offset') => 'This time occurs twice. Choose one of these UTC offsets: '.implode(' or ', array_keys($candidates)).'.',
            ]);
        }

        return reset($candidates);
    }
}
