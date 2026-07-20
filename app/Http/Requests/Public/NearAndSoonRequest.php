<?php

namespace App\Http\Requests\Public;

use App\Domain\Events\EventType;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class NearAndSoonRequest extends FormRequest
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
            'north' => ['nullable', 'numeric', 'between:-90,90', 'required_with:south,east,west'],
            'south' => ['nullable', 'numeric', 'between:-90,90', 'required_with:north,east,west'],
            'east' => ['nullable', 'numeric', 'between:-180,180', 'required_with:north,south,west'],
            'west' => ['nullable', 'numeric', 'between:-180,180', 'required_with:north,south,east'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $north = $this->input('north');
            $south = $this->input('south');
            if (is_numeric($north) && is_numeric($south) && (float) $north <= (float) $south) {
                $validator->errors()->add('north', 'North must be greater than south.');
            }

            $from = $this->input('from');
            $to = $this->input('to');
            if (! is_string($from) || ! is_string($to)
                || $validator->errors()->has('from')
                || $validator->errors()->has('to')
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

    /** @return array{q: ?string, type: list<string>, location: list<string>, from: ?string, to: ?string, ongoing: bool, north: ?float, south: ?float, east: ?float, west: ?float} */
    public function criteria(): array
    {
        $validated = $this->validated();

        return [
            'q' => $validated['q'] ?? null,
            'type' => array_values($validated['type'] ?? []),
            'location' => array_values($validated['location'] ?? []),
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
            'ongoing' => $this->boolean('ongoing'),
            'north' => isset($validated['north']) ? (float) $validated['north'] : null,
            'south' => isset($validated['south']) ? (float) $validated['south'] : null,
            'east' => isset($validated['east']) ? (float) $validated['east'] : null,
            'west' => isset($validated['west']) ? (float) $validated['west'] : null,
        ];
    }

    public function instant(): CarbonImmutable
    {
        return $this->validationInstant ??= Date::now('UTC')->toImmutable();
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['q', 'from', 'to', 'north', 'south', 'east', 'west'] as $key) {
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
}
