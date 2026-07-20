<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('promotes and verifies an existing user without changing their password', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'member@example.test',
        'password' => 'existing-password',
    ]);

    $this->artisan('user:make-admin', ['email' => ' MEMBER@example.test '])
        ->expectsOutput('Promoted [member@example.test] to administrator.')
        ->assertSuccessful();

    $user->refresh();

    expect($user->is_admin)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('existing-password', $user->password))->toBeTrue();
});

it('creates a verified administrator with a safely hashed password', function () {
    $this->artisan('user:make-admin', [
        'email' => 'new-admin@example.test',
        '--name' => 'New Administrator',
        '--password' => 'secure-password',
    ])
        ->expectsOutput('Created administrator [new-admin@example.test].')
        ->assertSuccessful();

    $user = User::query()->where('email', 'new-admin@example.test')->sole();

    expect($user->name)->toBe('New Administrator')
        ->and($user->is_admin)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->password)->not->toBe('secure-password')
        ->and(Hash::check('secure-password', $user->password))->toBeTrue();
});

it('is idempotent for an existing administrator', function () {
    User::factory()->admin()->create(['email' => 'admin@example.test']);

    $this->artisan('user:make-admin', ['email' => 'admin@example.test'])
        ->expectsOutput('Admin user [admin@example.test] is already configured.')
        ->assertSuccessful();

    expect(User::query()->where('email', 'admin@example.test')->count())->toBe(1);
});

it('rejects invalid input without creating a user', function (array $arguments) {
    $this->artisan('user:make-admin', $arguments)->assertFailed();

    expect(User::query()->count())->toBe(0);
})->with([
    'invalid email' => [[
        'email' => 'not-an-email',
        '--name' => 'Administrator',
        '--password' => 'secure-password',
    ]],
    'missing non-interactive creation fields' => [[
        'email' => 'admin@example.test',
        '--no-interaction' => true,
    ]],
    'short password' => [[
        'email' => 'admin@example.test',
        '--name' => 'Administrator',
        '--password' => 'short',
    ]],
]);
