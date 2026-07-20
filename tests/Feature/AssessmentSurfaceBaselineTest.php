<?php

use Inertia\Testing\AssertableInertia as Assert;

it('keeps the public assessment entry and health routes available', function () {
    $this->get('/')->assertRedirect(route('events.index'));
    $this->get('/up')->assertOk();
});

it('renders each named assessment surface through its intended Inertia component', function (string $routeName, string $component) {
    $this->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    'event explorer' => ['events.index', 'Events/Index'],
    'event visual one' => ['events.visual1', 'Events/VisualOne'],
    'event visual two' => ['events.visual2', 'Events/VisualTwo'],
    'dashboard' => ['dashboard', 'Dashboard'],
]);
