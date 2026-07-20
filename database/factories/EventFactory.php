<?php

namespace Database\Factories;

use App\Domain\Events\EventStatus;
use App\Domain\Events\EventType;
use App\Models\Event;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Event> */
class EventFactory extends Factory
{
    protected $model = Event::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $type = EventType::cases()[fake()->numberBetween(0, count(EventType::cases()) - 1)];
        $startsAt = CarbonImmutable::instance(fake()->dateTimeBetween('-1 year', '+1 year'))
            ->utc()
            ->startOfSecond();
        $endsAt = $startsAt->addHours(fake()->numberBetween(1, 72));
        $latitude = fake()->randomFloat(7, 52.48, 52.56);
        $longitude = fake()->randomFloat(7, 13.35, 13.46);
        $title = ucwords(rtrim(fake()->sentence(3), '.'));

        return [
            'user_id' => User::factory(),
            'title' => $title,
            'description' => fake()->paragraphs(2, true),
            'organizer_name' => fake()->company(),
            'venue_name' => fake()->company().' Hall',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'timezone' => 'Europe/Berlin',
            'starts_on_local' => $startsAt->setTimezone('Europe/Berlin')->toDateString(),
            'location_key' => 'de-berlin',
            'locality' => 'Berlin',
            'region' => null,
            'country' => 'Germany',
            'country_code' => 'DE',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'image_set_key' => $this->defaultImageSet($type),
            'status' => fake()->randomElement(EventStatus::values()),
            'type' => $type->value,
            'tags' => ['community', 'featured'],
            'minimum_price' => number_format(fake()->randomFloat(2, 0, 250), 2, '.', ''),
            'currency_code' => 'EUR',
            'capacity' => fake()->numberBetween(20, 50_000),
            'payload' => json_encode(['source' => 'factory', 'name' => $title], JSON_THROW_ON_ERROR),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => EventStatus::Published]);
    }

    public function soldOut(): static
    {
        return $this->state(fn (): array => ['status' => EventStatus::SoldOut]);
    }

    public function ended(): static
    {
        return $this->state(function (): array {
            $start = CarbonImmutable::now('UTC')->subDays(2)->startOfSecond();

            return [
                'starts_at' => $start,
                'ends_at' => $start->addHours(2),
                'starts_on_local' => $start->setTimezone('Europe/Berlin')->toDateString(),
            ];
        });
    }

    public function ongoing(): static
    {
        return $this->state(function (): array {
            $start = CarbonImmutable::now('UTC')->subHour()->startOfSecond();

            return [
                'starts_at' => $start,
                'ends_at' => $start->addHours(3),
                'starts_on_local' => $start->setTimezone('Europe/Berlin')->toDateString(),
            ];
        });
    }

    public function withoutCoordinates(): static
    {
        return $this->state(fn (): array => ['latitude' => null, 'longitude' => null]);
    }

    public function free(): static
    {
        return $this->state(fn (): array => ['minimum_price' => '0.00', 'currency_code' => 'EUR']);
    }

    public function withoutCapacity(): static
    {
        return $this->state(fn (): array => ['capacity' => null]);
    }

    private function defaultImageSet(EventType $type): string
    {
        return match ($type) {
            EventType::Concert => 'concert-industrial-after-dark',
            EventType::Conference => 'conference-timber-ideas-forum',
            EventType::Meetup => 'meetup-neighborhood-makers-table',
            EventType::Workshop => 'workshop-ceramic-studio',
            EventType::Festival => 'festival-garden-long-table',
            EventType::Sports => 'sports-community-track-evening',
            EventType::Networking => 'networking-architecture-studio-social',
            EventType::Exhibition => 'exhibition-adaptive-reuse-opening',
        };
    }
}
