<?php

use App\Http\Controllers\Account\MyEventsController;
use App\Http\Controllers\AccountRedirectController;
use App\Http\Controllers\Admin\AddressSearchController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EventAttendeeController;
use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\DiscoverController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\NearAndSoonController;
use App\Http\Controllers\SignedAttendanceController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:120,1')->group(function () {
    Route::get('/', DiscoverController::class)->name('home');
    Route::get('events-visual-1', DiscoverController::class)->name('events.visual1');
    Route::get('events-visual-2', NearAndSoonController::class)->name('events.visual2');
});

Route::redirect('events', '/admin/events')->name('events.index');
Route::get('events/{event}', [EventController::class, 'show'])
    ->middleware('throttle:120,1')
    ->name('events.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('events/{event}/attendance', [AttendanceController::class, 'begin'])
        ->middleware('throttle:120,1')
        ->name('attendance.begin');
    Route::get('events/{event}/attendance/status', [AttendanceController::class, 'status'])
        ->middleware('throttle:60,1')
        ->name('attendance.status');
    Route::put('events/{event}/attendance', [AttendanceController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('attendance.store');
    Route::delete('events/{event}/attendance', [AttendanceController::class, 'destroy'])
        ->middleware('throttle:20,1')
        ->name('attendance.destroy');

    Route::get('my-events', MyEventsController::class)->name('account.events.index');
});

Route::middleware(['signed', 'throttle:10,1'])->group(function () {
    Route::get('attendance/{attendance}/cancel', [SignedAttendanceController::class, 'confirm'])
        ->name('attendance.cancel.confirm');
    Route::delete('attendance/{attendance}/cancel', [SignedAttendanceController::class, 'destroy'])
        ->name('attendance.cancel.destroy');
});

Route::redirect('dashboard', '/admin');

Route::get('account', AccountRedirectController::class)->middleware('auth')->name('account');

Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('address-search', AddressSearchController::class)
        ->middleware('throttle:20,1')
        ->name('admin.address-search');
    Route::get('events', [AdminEventController::class, 'index'])->name('admin.events.index');
    Route::get('events/create', [AdminEventController::class, 'create'])->name('admin.events.create');
    Route::post('events', [AdminEventController::class, 'store'])->name('admin.events.store');
    Route::get('events/{event}/edit', [AdminEventController::class, 'edit'])->name('admin.events.edit');
    Route::put('events/{event}', [AdminEventController::class, 'update'])->name('admin.events.update');
    Route::delete('events/{event}', [AdminEventController::class, 'destroy'])->name('admin.events.destroy');
    Route::get('events/{event}', [AdminEventController::class, 'show'])->name('admin.events.show');
    Route::get('events/{event}/attendees', EventAttendeeController::class)
        ->name('admin.events.attendees');
});

require __DIR__.'/settings.php';
