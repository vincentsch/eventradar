<?php

use App\Domain\Attendance\DeliveryStatus;
use App\Jobs\SendAttendanceReminder;
use App\Mail\EventReminderMail;
use App\Models\AttendanceDelivery;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\User;
use App\Services\Attendance\AttendanceManager;
use App\Services\Attendance\ReminderDispatcher;
use App\Services\Events\EventImageCatalogueImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(EventImageCatalogueImporter::class)->replace();
    Carbon::setTestNow('2026-08-01 12:00:00 UTC');
});

afterEach(function () {
    Carbon::setTestNow();
});

function attendanceForReminder(): EventAttendance
{
    Queue::fake();
    $user = User::factory()->create();
    $event = Event::factory()->published()->create([
        'starts_at' => now('UTC')->addDays(10),
        'ends_at' => now('UTC')->addDays(10)->addHours(2),
        'starts_on_local' => now('UTC')->addDays(10)->toDateString(),
    ]);

    test()->actingAs($user)->put("/events/{$event->id}/attendance", ['intent' => 'going']);

    return EventAttendance::query()->with(['user', 'event'])->sole();
}

it('claims a due three-day reminder once', function () {
    $attendance = attendanceForReminder();
    Queue::fake();
    Carbon::setTestNow($attendance->event->starts_at->subDays(3));

    $dispatcher = app(ReminderDispatcher::class);

    expect($dispatcher->dispatchDue())->toBe(1)
        ->and($dispatcher->dispatchDue())->toBe(0);

    Queue::assertPushed(SendAttendanceReminder::class, 1);
    expect(AttendanceDelivery::query()
        ->where('kind', 'three_days')
        ->firstOrFail()->status)->toBe(DeliveryStatus::Processing);
});

it('sends and records a claimed reminder', function (string $kind) {
    $attendance = attendanceForReminder();
    Queue::fake();
    Mail::fake();
    Carbon::setTestNow($kind === 'three_days'
        ? $attendance->event->starts_at->subDays(3)
        : $attendance->event->starts_at->subDay());

    app(ReminderDispatcher::class)->dispatchDue();
    $delivery = AttendanceDelivery::query()
        ->where('kind', $kind)
        ->firstOrFail();

    (new SendAttendanceReminder($delivery->id, $delivery->claim_token))->handle();

    Mail::assertSent(EventReminderMail::class, fn ($mail) => $mail->hasTo($attendance->user->email) && $mail->kind->value === $kind
    );
    expect($delivery->fresh()->status)->toBe(DeliveryStatus::Sent)
        ->and($delivery->fresh()->sent_at)->not->toBeNull();
})->with(['three_days', 'one_day']);

it('never queues reminders after attendance is cancelled', function () {
    $attendance = attendanceForReminder();
    test()->actingAs($attendance->user)
        ->delete("/events/{$attendance->event_id}/attendance");
    Queue::fake();
    Carbon::setTestNow($attendance->event->starts_at->subDays(3));

    expect(app(ReminderDispatcher::class)->dispatchDue())->toBe(0);
    Queue::assertNothingPushed();
});

it('skips a reminder horizon that passed before registration', function () {
    Queue::fake();
    $user = User::factory()->create();
    $event = Event::factory()->published()->create([
        'starts_at' => now('UTC')->addDays(2),
        'ends_at' => now('UTC')->addDays(2)->addHours(2),
        'starts_on_local' => now('UTC')->addDays(2)->toDateString(),
    ]);

    $this->actingAs($user)->put("/events/{$event->id}/attendance", ['intent' => 'going']);

    expect(AttendanceDelivery::query()
        ->where('kind', 'three_days')
        ->firstOrFail()->status)->toBe(DeliveryStatus::Skipped)
        ->and(AttendanceDelivery::query()
            ->where('kind', 'one_day')
            ->firstOrFail()->status)->toBe(DeliveryStatus::Pending);
});

it('skips reminders whose delivery window has already passed', function () {
    $attendance = attendanceForReminder();
    Queue::fake();
    Carbon::setTestNow($attendance->event->starts_at->subDays(2));

    expect(app(ReminderDispatcher::class)->dispatchDue())->toBe(0)
        ->and(AttendanceDelivery::query()
            ->where('kind', 'three_days')
            ->firstOrFail()->status)->toBe(DeliveryStatus::Skipped);

    Queue::assertNothingPushed();
});

it('rebuilds pending reminder horizons when an event is rescheduled', function () {
    $attendance = attendanceForReminder();
    Queue::fake();
    $oldOneDay = AttendanceDelivery::query()
        ->where('kind', 'one_day')
        ->firstOrFail();

    $attendance->event->forceFill([
        'starts_at' => $attendance->event->starts_at->addDays(5),
        'ends_at' => $attendance->event->ends_at->addDays(5),
        'starts_on_local' => $attendance->event->starts_on_local->addDays(5),
    ])->save();
    app(AttendanceManager::class)->rescheduleForEvent($attendance->event);

    $attendance->refresh();
    $newOneDay = AttendanceDelivery::query()
        ->where('attendance_revision', 2)
        ->where('kind', 'one_day')
        ->firstOrFail();

    expect($attendance->revision)->toBe(2)
        ->and($oldOneDay->fresh()->status)->toBe(DeliveryStatus::Skipped)
        ->and($newOneDay->status)->toBe(DeliveryStatus::Pending)
        ->and($newOneDay->due_at->equalTo($attendance->event->starts_at->subDay()))->toBeTrue();
});

it('never recreates a sent confirmation after repeated rescheduling', function () {
    $attendance = attendanceForReminder();
    AttendanceDelivery::query()
        ->where('kind', 'confirmation')
        ->update(['status' => DeliveryStatus::Sent->value, 'sent_at' => now('UTC')]);

    foreach ([2, 4] as $days) {
        $attendance->event->forceFill([
            'starts_at' => $attendance->event->starts_at->addDays($days),
            'ends_at' => $attendance->event->ends_at->addDays($days),
            'starts_on_local' => $attendance->event->starts_on_local->addDays($days),
        ])->save();
        app(AttendanceManager::class)->rescheduleForEvent($attendance->event);
        $attendance->refresh();
    }

    expect($attendance->revision)->toBe(3)
        ->and(AttendanceDelivery::query()->where('kind', 'confirmation')->count())->toBe(1)
        ->and(AttendanceDelivery::query()
            ->where('attendance_revision', 3)
            ->whereIn('kind', ['three_days', 'one_day'])
            ->where('status', DeliveryStatus::Pending->value)
            ->count())->toBe(2);
});
