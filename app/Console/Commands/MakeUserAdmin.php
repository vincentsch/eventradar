<?php

namespace App\Console\Commands;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class MakeUserAdmin extends Command
{
    use PasswordValidationRules;

    /** @var string */
    protected $signature = 'user:make-admin
        {email : Email address of the administrator}
        {--name= : Name to use when creating a new user}
        {--password= : Password for a new user or an intentional password reset}';

    /** @var string */
    protected $description = 'Promote an existing user or create a verified administrator';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        if (! $this->validateEmail($email)) {
            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user instanceof User) {
            return $this->promote($user);
        }

        return $this->createAdmin($email);
    }

    private function promote(User $user): int
    {
        $wasAdmin = $user->is_admin;
        $user->is_admin = true;
        $user->email_verified_at ??= Carbon::now();

        $password = $this->optionString('password', trim: false);
        if ($password !== null) {
            if (! $this->validatePassword($password, $password)) {
                return self::FAILURE;
            }

            $user->password = $password;
        }

        $user->save();

        if ($wasAdmin && $password === null) {
            $this->info("Admin user [{$user->email}] is already configured.");
        } else {
            $this->info("Promoted [{$user->email}] to administrator.");
        }

        if ($password !== null) {
            $this->warn('The password was updated because --password was provided.');
        }

        return self::SUCCESS;
    }

    private function createAdmin(string $email): int
    {
        $name = $this->optionString('name');
        $password = $this->optionString('password', trim: false);
        $confirmation = $password;

        if ($name === null && $this->input->isInteractive()) {
            $name = trim((string) $this->ask('Name'));
        }

        if ($password === null && $this->input->isInteractive()) {
            $password = (string) $this->secret('Password');
            $confirmation = (string) $this->secret('Confirm password');
        }

        if ($name === null || $password === null || $confirmation === null) {
            $this->error('Creating a user non-interactively requires --name and --password.');

            return self::FAILURE;
        }

        $validator = Validator::make([
            'name' => $name,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'name' => ['required', 'string', 'max:100'],
            'password' => $this->passwordRules(),
        ]);

        if ($validator->fails()) {
            $this->renderErrors($validator->errors()->all());

            return self::FAILURE;
        }

        $user = new User([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);
        $user->is_admin = true;
        $user->email_verified_at = Carbon::now();
        $user->save();

        $this->info("Created administrator [{$user->email}].");

        return self::SUCCESS;
    }

    private function validateEmail(string $email): bool
    {
        $validator = Validator::make(['email' => $email], [
            'email' => ['required', 'string', 'lowercase', 'email', 'max:254'],
        ]);

        if ($validator->fails()) {
            $this->renderErrors($validator->errors()->all());

            return false;
        }

        return true;
    }

    private function validatePassword(string $password, string $confirmation): bool
    {
        $validator = Validator::make([
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'password' => $this->passwordRules(),
        ]);

        if ($validator->fails()) {
            $this->renderErrors($validator->errors()->all());

            return false;
        }

        return true;
    }

    /** @param array<array-key, string> $errors */
    private function renderErrors(array $errors): void
    {
        foreach ($errors as $error) {
            $this->error($error);
        }
    }

    private function optionString(string $name, bool $trim = true): ?string
    {
        $value = $this->option($name);

        if (! is_string($value)) {
            return null;
        }

        if ($trim) {
            $value = trim($value);
        }

        return $value === '' ? null : $value;
    }
}
