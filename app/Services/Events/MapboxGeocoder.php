<?php

namespace App\Services\Events;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class MapboxGeocoder
{
    /** @return list<array<string, float|string|null>> */
    public function forward(string $query): array
    {
        return $this->request('forward', [
            'q' => $query,
            'autocomplete' => 'false',
            'types' => 'address,street,place',
            'limit' => 5,
        ]);
    }

    /** @return array<string, float|string|null>|null */
    public function reverse(float $latitude, float $longitude): ?array
    {
        return $this->request('reverse', [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'types' => 'address,street,place',
            'limit' => 1,
        ])[0] ?? null;
    }

    /**
     * @param  array<string, float|int|string>  $parameters
     * @return list<array<string, float|string|null>>
     */
    private function request(string $endpoint, array $parameters): array
    {
        $token = (string) config('services.mapbox.geocoding_token');
        if ($token === '') {
            throw new RuntimeException('Mapbox geocoding is not configured.');
        }

        $response = Http::acceptJson()
            ->timeout(5)
            ->retry(2, 150)
            ->get("https://api.mapbox.com/search/geocode/v6/{$endpoint}", [
                ...$parameters,
                'access_token' => $token,
                'permanent' => 'true',
            ])
            ->throw();

        return collect($response->json('features', []))
            ->filter(fn ($feature): bool => is_array($feature))
            ->map(fn (array $feature): array => $this->result($feature))
            ->filter(fn (array $result): bool => $result['formatted_address'] !== ''
                && $result['latitude'] !== null
                && $result['longitude'] !== null)
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $feature */
    private function result(array $feature): array
    {
        $properties = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
        $coordinates = Arr::get($feature, 'geometry.coordinates', []);
        $context = is_array($properties['context'] ?? null) ? $properties['context'] : [];

        return [
            'formatted_address' => (string) ($properties['full_address'] ?? $properties['place_formatted'] ?? $properties['name'] ?? ''),
            'address_line_1' => $this->contextName($context, 'address')
                ?? (isset($properties['name']) ? (string) $properties['name'] : null),
            'postal_code' => $this->contextName($context, 'postcode'),
            'locality' => $this->contextName($context, 'place')
                ?? $this->contextName($context, 'locality')
                ?? (string) ($properties['name'] ?? ''),
            'region' => $this->contextName($context, 'region'),
            'country' => $this->contextName($context, 'country') ?? '',
            'country_code' => strtoupper((string) Arr::get($context, 'country.country_code', '')),
            'latitude' => isset($coordinates[1]) && is_numeric($coordinates[1]) ? (float) $coordinates[1] : null,
            'longitude' => isset($coordinates[0]) && is_numeric($coordinates[0]) ? (float) $coordinates[0] : null,
        ];
    }

    /** @param array<string, mixed> $context */
    private function contextName(array $context, string $key): ?string
    {
        $value = Arr::get($context, $key.'.name');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
