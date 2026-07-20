<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    private const REGISTRATIONS_PER_MINUTE = 5;

    /**
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:254',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        $rateLimitKey = 'registration:'.request()->ip();

        if (RateLimiter::tooManyAttempts($rateLimitKey, self::REGISTRATIONS_PER_MINUTE)) {
            throw ValidationException::withMessages([
                'email' => 'Too many accounts were created from this address. Try again in a minute.',
            ]);
        }

        RateLimiter::hit($rateLimitKey, 60);

        return User::create([
            'name' => trim($input['name']),
            'email' => mb_strtolower(trim($input['email'])),
            'password' => $input['password'],
        ]);
    }
}
