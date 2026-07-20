<?php

namespace App\Services\Events;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class MapboxGeocoder
{
    /**
     * @return list<array{formatted_address: string, address_line_1: ?string, postal_code: ?string, locality: string, region: ?string, country: string, country_code: string, latitude: ?float, longitude: ?float}>
     */
    public function forward(string $query): array
    {
        return $this->request('forward', [
            'q' => $query,
            'autocomplete' => 'true',
            'types' => 'address,street,place',
            'limit' => 5,
        ]);
    }

    /**
     * @return array{formatted_address: string, address_line_1: ?string, postal_code: ?string, locality: string, region: ?string, country: string, country_code: string, latitude: ?float, longitude: ?float}|null
     */
    public function reverse(float $latitude, float $longitude): ?array
    {
        return $this->request('reverse', [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'types' => 'address,street,place',
        ])[0] ?? null;
    }

    /**
     * @param  array<string, float|int|string>  $parameters
     * @return list<array{formatted_address: string, address_line_1: ?string, postal_code: ?string, locality: string, region: ?string, country: string, country_code: string, latitude: ?float, longitude: ?float}>
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

        $features = $response->json('features', []);
        if (! is_array($features)) {
            return [];
        }

        $results = [];
        foreach ($features as $feature) {
            if (! is_array($feature)) {
                continue;
            }

            $result = $this->result($feature);
            if ($result['formatted_address'] !== ''
                && $result['latitude'] !== null
                && $result['longitude'] !== null
            ) {
                $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $feature
     * @return array{formatted_address: string, address_line_1: ?string, postal_code: ?string, locality: string, region: ?string, country: string, country_code: string, latitude: ?float, longitude: ?float}
     */
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
