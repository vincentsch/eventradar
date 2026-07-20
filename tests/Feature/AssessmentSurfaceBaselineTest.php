<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('keeps the public assessment entry and health routes available', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Public/Discover'));
    $this->get('/up')->assertOk();
});

it('adds crawler exclusion headers when indexing is disabled', function () {
    config()->set('app.prevent_indexing', true);

    $this->get('/')
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
});

it('adds defensive browser headers to web responses', function () {
    $this->get('/')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
});

it('renders each named public visual through its intended Inertia component', function (string $routeName, string $component) {
    $this->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    'event visual one' => ['events.visual1', 'Public/Discover'],
    'event visual two' => ['events.visual2', 'Public/NearAndSoon'],
]);

it('redirects guests from the inherited workspace entries to login', function (string $url) {
    $this->get($url)->assertRedirect(route('login'));
})->with(['/admin', '/admin/events']);

it('keeps legacy workspace entries as redirects into admin', function () {
    $this->get('/dashboard')->assertRedirect('/admin');
    $this->get('/events')->assertRedirect('/admin/events');
});

it('keeps settings under admin while passkey discovery stays root anchored', function () {
    expect(route('profile.edit', absolute: false))->toBe('/admin/settings/profile')
        ->and(route('appearance.edit', absolute: false))->toBe('/admin/settings/appearance');

    $this->get('/.well-known/passkey-endpoints')
        ->assertOk()
        ->assertJsonPath('enroll', route('security.edit'))
        ->assertJsonPath('manage', route('security.edit'));
});

it('exposes public account registration for attendee accounts', function () {
    $this->get('/register')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/Register'));
});
