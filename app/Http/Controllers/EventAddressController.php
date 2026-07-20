<?php

namespace App\Http\Controllers;

use App\Domain\Events\EventStatus;
use App\Models\Event;
use App\Services\Events\MapboxGeocoder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Date;
use RuntimeException;

class EventAddressController extends Controller
{
    public function __invoke(string $event, MapboxGeocoder $geocoder): JsonResponse
    {
        $record = Event::query()
            ->whereKey($event)
            ->whereIn('status', EventStatus::publicValues())
            ->where('ends_at', '>', Date::now('UTC'))
            ->firstOrFail([
                'id', 'formatted_address', 'address_line_1', 'postal_code',
                'locality', 'region', 'country', 'country_code', 'latitude', 'longitude',
            ]);

        if ($record->formatted_address !== null || $record->latitude === null || $record->longitude === null) {
            return response()->json(['address' => $record->formatted_address]);
        }

        try {
            $address = $geocoder->reverse($record->latitude, $record->longitude);
        } catch (RuntimeException) {
            return response()->json(['address' => null]);
        }
        if ($address === null) {
            return response()->json(['address' => null]);
        }

        $record->update([
            'formatted_address' => $address['formatted_address'],
            'address_line_1' => $address['address_line_1'],
            'postal_code' => $address['postal_code'],
            'locality' => $address['locality'] ?: $record->locality,
            'region' => $address['region'] ?: $record->region,
            'country' => $address['country'] ?: $record->country,
            'country_code' => $address['country_code'] ?: $record->country_code,
        ]);

        return response()->json(['address' => $record->formatted_address]);
    }
}
