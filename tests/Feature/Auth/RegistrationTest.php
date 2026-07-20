<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders public registration', function () {
    $this->get('/register')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/Register'));
});

it('creates a normal user and sends them to verify their email', function () {
    $response = $this->post('/register', [
        'name' => 'Event Guest',
        'email' => 'guest@example.test',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]);

    // A fresh account must verify first; routing it through /account would
    // hit the verified middleware and discard the intended URL that started
    // the sign-up (for example an event's attendance action).
    $response->assertRedirect(route('verification.notice', absolute: false));
    $this->assertAuthenticated();

    $user = User::query()->where('email', 'guest@example.test')->firstOrFail();
    expect($user->is_admin)->toBeFalse();
    $this->get('/account')->assertRedirect('/my-events');
});

it('keeps normal users out of the admin workspace', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertForbidden();
});

it('routes administrators to the admin workspace', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/account')
        ->assertRedirect('/admin');
});

it('rate limits account creation by client address', function () {
    foreach (range(1, 5) as $number) {
        $this->post('/register', [
            'name' => "Guest {$number}",
            'email' => "guest-{$number}@example.test",
            'password' => 'correct-horse-battery-staple',
            'password_confirmation' => 'correct-horse-battery-staple',
        ])->assertRedirect(route('verification.notice', absolute: false));

        $this->post('/logout');
    }

    $this->from('/register')->post('/register', [
        'name' => 'One Guest Too Many',
        'email' => 'guest-6@example.test',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ])
        ->assertRedirect('/register')
        ->assertSessionHasErrors('email');

    $this->assertDatabaseMissing('users', ['email' => 'guest-6@example.test']);
});
