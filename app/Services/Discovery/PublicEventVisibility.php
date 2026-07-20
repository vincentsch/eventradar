<?php

namespace App\Services\Discovery;

use App\Domain\Events\EventStatus;
use App\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class PublicEventVisibility
{
    public const MAX_DURATION_HOURS = 72;

    /**
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function apply(
        Builder $query,
        CarbonImmutable $instant,
        bool $useCursorAccessPath = false,
        bool $useGeneratedStatus = false,
    ): Builder {
        if (($useCursorAccessPath || $useGeneratedStatus) && DB::connection()->getDriverName() === 'mysql') {
            $query->where('is_public', true);
        } else {
            $query->whereIn('status', EventStatus::publicValues());
        }

        if ($useCursorAccessPath) {
            $query->where('starts_at', '>=', $instant->subHours(self::MAX_DURATION_HOURS));
        }

        return $query->where('ends_at', '>', $instant);
    }
}
