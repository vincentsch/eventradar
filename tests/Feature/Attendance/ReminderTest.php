<?php

use App\Domain\Attendance\DeliveryStatus;
use App\Jobs\SendAttendanceReminder;
use App\Mail\EventReminderMail;
use App\Models\AttendanceDelivery;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\User;
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

it('sends and records a claimed reminder', function () {
    $attendance = attendanceForReminder();
    Queue::fake();
    Mail::fake();
    Carbon::setTestNow($attendance->event->starts_at->subDay());

    app(ReminderDispatcher::class)->dispatchDue();
    $delivery = AttendanceDelivery::query()
        ->where('kind', 'one_day')
        ->firstOrFail();

    (new SendAttendanceReminder($delivery->id, $delivery->claim_token))->handle();

    Mail::assertSent(EventReminderMail::class, fn ($mail) => $mail->hasTo($attendance->user->email) && $mail->kind->value === 'one_day'
    );
    expect($delivery->fresh()->status)->toBe(DeliveryStatus::Sent)
        ->and($delivery->fresh()->sent_at)->not->toBeNull();
});

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
