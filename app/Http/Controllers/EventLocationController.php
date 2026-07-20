<?php

namespace App\Http\Controllers;

use App\Domain\Events\EventStatus;
use App\Models\Event;
use App\Services\Events\MapboxGeocoder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use RuntimeException;

class EventLocationController extends Controller
{
    public function __invoke(string $event, MapboxGeocoder $geocoder): JsonResponse
    {
        $record = Event::query()
            ->select([
                'id', 'venue_name', 'formatted_address', 'address_line_1',
                'postal_code', 'locality', 'region', 'country', 'latitude', 'longitude',
            ])
            ->whereKey($event)
            ->whereIn('status', EventStatus::publicValues())
            ->where('ends_at', '>', Date::now('UTC'))
            ->firstOrFail();

        abort_if($record->latitude === null || $record->longitude === null, 404);

        if ($record->formatted_address !== null && $record->formatted_address !== '') {
            return response()->json($this->response(
                $record,
                $record->formatted_address,
                $record->address_line_1 !== null ? 'stored' : 'coordinates',
            ));
        }

        $cacheKey = sprintf(
            'events:location:v1:%s:%s:%s',
            $record->id,
            $record->latitude,
            $record->longitude,
        );
        $resolved = Cache::get($cacheKey);

        if (! is_array($resolved)) {
            try {
                $resolved = $geocoder->reverse((float) $record->latitude, (float) $record->longitude);
            } catch (RuntimeException $exception) {
                report($exception);
                $resolved = null;
            }

            if ($resolved !== null) {
                // The geocoding request uses Mapbox's permanent mode, so the
                // result may be cached instead of spending one request per view.
                Cache::put($cacheKey, $resolved, now()->addDays(90));
            }
        }

        $address = is_array($resolved)
            ? (string) ($resolved['formatted_address'] ?? '')
            : collect([$record->locality, $record->region, $record->country])
                ->filter()
                ->implode(', ');

        return response()->json($this->response(
            $record,
            $address,
            is_array($resolved) ? 'reverse' : 'coordinates',
        ));
    }

    /** @return array<string, bool|float|string> */
    private function response(Event $event, string $address, string $resolution): array
    {
        return [
            'venue' => $event->venue_name,
            'address' => $address,
            'latitude' => (float) $event->latitude,
            'longitude' => (float) $event->longitude,
            'resolution' => $resolution,
            'approximate' => $resolution !== 'stored',
        ];
    }
}
