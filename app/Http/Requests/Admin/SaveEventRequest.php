<?php

namespace App\Http\Requests\Admin;

use App\Domain\Events\EventStatus;
use App\Domain\Events\EventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'country_code' => strtoupper($this->string('country_code')->trim()->toString()),
            'currency_code' => ($currency = $this->string('currency_code')->trim()->toString()) === ''
                ? null
                : strtoupper($currency),
            'starts_at_offset' => $this->string('starts_at_offset')->trim()->toString() ?: null,
            'ends_at_offset' => $this->string('ends_at_offset')->trim()->toString() ?: null,
        ]);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $images = $this->routeIs('admin.events.store') ? ['required'] : ['sometimes'];

        return [
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:10000'],
            'organizer_name' => ['required', 'string', 'max:120'],
            'venue_name' => ['required', 'string', 'max:120'],
            'formatted_address' => ['required', 'string', 'max:320'],
            'address_line_1' => ['nullable', 'string', 'max:180'],
            'postal_code' => ['nullable', 'string', 'max:24'],
            'locality' => ['required', 'string', 'max:80'],
            'region' => ['nullable', 'string', 'max:80'],
            'country' => ['required', 'string', 'max:80'],
            'country_code' => ['required', 'string', 'size:2', 'alpha:ascii'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
            'starts_at_local' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at_local' => ['required', 'date_format:Y-m-d\TH:i'],
            'starts_at_offset' => ['nullable', 'regex:/^[+-](?:0\d|1\d|2[0-3]):[0-5]\d$/'],
            'ends_at_offset' => ['nullable', 'regex:/^[+-](?:0\d|1\d|2[0-3]):[0-5]\d$/'],
            'status' => ['required', Rule::enum(EventStatus::class)],
            'type' => ['required', Rule::enum(EventType::class)],
            'tags' => ['present', 'array', 'max:12'],
            'tags.*' => ['string', 'max:40', 'distinct:ignore_case'],
            'minimum_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'required_with:currency_code'],
            'currency_code' => ['nullable', 'string', 'size:3', 'alpha:ascii', 'required_with:minimum_price'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'images' => [...$images, 'array', 'min:2', 'max:8'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp,gif,avif', 'max:12288', 'dimensions:max_width=20000,max_height=20000'],
        ];
    }
}
