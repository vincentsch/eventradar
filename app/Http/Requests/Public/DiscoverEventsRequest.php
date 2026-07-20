<?php

namespace App\Http\Requests\Public;

use App\Domain\Events\EventType;
use App\Services\Discovery\PublicEventQuery;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DiscoverEventsRequest extends FormRequest
{
    private ?CarbonImmutable $validationInstant = null;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100', $this->validUtf8()],
            'type' => ['nullable', 'array', 'max:8'],
            'type.*' => [Rule::enum(EventType::class)],
            'location' => ['nullable', 'array', 'max:50'],
            'location.*' => ['string', 'max:64', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'ongoing' => ['nullable', 'boolean'],
            'cursor' => ['nullable', 'string', 'max:512', $this->validCursor()],
            'page' => ['nullable', 'integer', 'min:1', 'max:56'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $hasDiscovery = collect(['q', 'type', 'location', 'from', 'to'])
                ->contains(fn (string $key): bool => $this->filled($key));

            if ($hasDiscovery && $this->input('cursor') !== null) {
                $validator->errors()->add('cursor', 'A feed cursor cannot be combined with discovery fields.');
            }

            if (! $hasDiscovery && $this->input('page') !== null) {
                $validator->errors()->add('page', 'A search page requires at least one discovery field.');
            }

            $from = $this->input('from');
            $to = $this->input('to');

            if ($validator->errors()->has('from')
                || $validator->errors()->has('to')
                || ! is_string($from)
                || ! is_string($to)
            ) {
                return;
            }

            $start = CarbonImmutable::createFromFormat('!Y-m-d', $from, 'UTC');
            $end = CarbonImmutable::createFromFormat('!Y-m-d', $to, 'UTC');

            if ($end->isBefore($start)) {
                $validator->errors()->add('to', 'The end date must be on or after the start date.');
            } elseif ($start->diffInDays($end) > 93) {
                $validator->errors()->add('to', 'The date range may not exceed 93 days.');
            }
        }];
    }

    public function queryValue(): PublicEventQuery
    {
        $validated = $this->validated();

        return new PublicEventQuery(
            search: $validated['q'] ?? null,
            types: array_values($validated['type'] ?? []),
            locations: array_values($validated['location'] ?? []),
            from: $validated['from'] ?? null,
            to: $validated['to'] ?? null,
            includeOngoing: $this->boolean('ongoing'),
            cursor: $validated['cursor'] ?? null,
            page: (int) ($validated['page'] ?? 1),
        );
    }

    public function instant(): CarbonImmutable
    {
        return $this->validationInstant ??= Date::now('UTC')->toImmutable();
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['q', 'from', 'to', 'cursor'] as $key) {
            $value = $this->input($key);
            if (is_string($value)) {
                $value = trim($value);
                $normalized[$key] = $value === '' ? null : $value;
            }
        }

        foreach (['type', 'location'] as $key) {
            $value = $this->input($key);

            if (is_string($value)) {
                $trimmed = trim($value);
                $normalized[$key] = $trimmed === '' ? [] : [$trimmed];
            } elseif (is_array($value)) {
                $normalized[$key] = array_values(array_unique(array_map(
                    fn (mixed $item): mixed => is_string($item) ? trim($item) : $item,
                    $value,
                ), SORT_REGULAR));
            }
        }

        $this->merge($normalized);
    }

    private function validUtf8(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && ! mb_check_encoding($value, 'UTF-8')) {
                $fail("The {$attribute} field must contain valid UTF-8 text.");
            }
        };
    }

    private function validCursor(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            $cursor = Cursor::fromEncoded($value);
            if ($cursor === null) {
                $fail("The {$attribute} field is invalid.");

                return;
            }

            $parameters = $cursor->toArray();
            $expectedKeys = ['starts_at', 'id', '_pointsToNextItems'];
            $unexpectedKeys = array_diff(array_keys($parameters), $expectedKeys);
            $startsAt = $parameters['starts_at'] ?? null;
            $id = $parameters['id'] ?? null;
            $direction = $parameters['_pointsToNextItems'] ?? null;
            $date = is_string($startsAt)
                ? DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $startsAt)
                : false;

            if (count($parameters) !== count($expectedKeys)
                || $unexpectedKeys !== []
                || $date === false
                || $date->format('Y-m-d H:i:s') !== $startsAt
                || ! is_string($id)
                || ! Str::isUuid($id)
                || ! is_bool($direction)
            ) {
                $fail("The {$attribute} field is invalid.");
            }
        };
    }
}
