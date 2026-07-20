<?php

use App\Http\Controllers\EventController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Public/Discover')->name('home');

Route::inertia('events-visual-1', 'Public/Discover')->name('events.visual1');
Route::inertia('events-visual-2', 'Public/NearAndSoon')->name('events.visual2');

Route::get('events', [EventController::class, 'index'])->name('events.index');
Route::get('events/data', [EventController::class, 'data'])->name('events.data');
Route::get('events/{event}', [EventController::class, 'show'])->name('events.show');

Route::inertia('dashboard', 'Dashboard')->name('dashboard');

require __DIR__.'/settings.php';
