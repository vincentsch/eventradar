<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\EventController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Public/Discover')->name('home');

Route::inertia('events-visual-1', 'Public/Discover')->name('events.visual1');
Route::inertia('events-visual-2', 'Public/NearAndSoon')->name('events.visual2');

Route::redirect('events', '/admin/events')->name('events.index');
Route::get('events/{event}', [EventController::class, 'show'])->name('events.show');

Route::redirect('dashboard', '/admin');

Route::middleware('auth')->prefix('admin')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('events', [AdminEventController::class, 'index'])->name('admin.events.index');
    Route::get('events/{event}', [AdminEventController::class, 'show'])->name('admin.events.show');
});

require __DIR__.'/settings.php';
