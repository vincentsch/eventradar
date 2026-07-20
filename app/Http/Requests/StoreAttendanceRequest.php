<?php

namespace App\Http\Requests;

use App\Domain\Attendance\AttendanceIntent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'intent' => ['required', Rule::enum(AttendanceIntent::class)],
        ];
    }

    public function intent(): AttendanceIntent
    {
        return AttendanceIntent::from($this->string('intent')->toString());
    }
}
