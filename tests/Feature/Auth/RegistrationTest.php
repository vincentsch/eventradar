<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders public registration', function () {
    $this->get('/register')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/Register'));
});

it('creates a normal user and sends them to their account area', function () {
    $response = $this->post('/register', [
        'name' => 'Event Guest',
        'email' => 'guest@example.test',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
    ]);

    $response->assertRedirect('/account');
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
