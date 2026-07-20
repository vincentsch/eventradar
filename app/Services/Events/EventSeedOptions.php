<?php

namespace App\Services\Events;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use LogicException;

final readonly class EventSeedOptions
{
    public function __construct(
        public string $profile,
        public int $rowCount,
        public string $seed,
        public CarbonImmutable $referenceAt,
    ) {}

    public static function fromConfig(): self
    {
        $profile = config('events.seed_profile');
        $profiles = config('events.seed_profiles');
        $seed = config('events.seed');
        $reference = config('events.seed_reference_at');

        if (! is_string($profile) || ! is_array($profiles) || ! isset($profiles[$profile])) {
            throw new InvalidArgumentException('EVENT_SEED_PROFILE must be one of: smoke, dev, full.');
        }

        $rowCount = $profiles[$profile];

        if (! is_int($rowCount) || $rowCount <= 0) {
            throw new InvalidArgumentException("Seed profile [{$profile}] must contain a positive integer row count.");
        }

        if ($profile === 'full' && config('events.allow_full_seed') !== true) {
            throw new LogicException('The full seed requires EVENT_SEED_ALLOW_FULL=true.');
        }

        if (! is_string($seed) || trim($seed) === '') {
            throw new InvalidArgumentException('EVENT_SEED must be a non-empty string.');
        }

        if (! is_string($reference) || ! preg_match('/(?:Z|[+-]\d{2}:\d{2})$/', $reference)) {
            throw new InvalidArgumentException('EVENT_SEED_REFERENCE_AT must be an ISO-8601 instant with an explicit offset.');
        }

        try {
            $referenceAt = CarbonImmutable::parse($reference)->utc()->startOfSecond();
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException('EVENT_SEED_REFERENCE_AT is not a valid instant.', previous: $exception);
        }

        return new self($profile, $rowCount, trim($seed), $referenceAt);
    }
}
