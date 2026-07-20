<?php

use App\Domain\Attendance\DeliveryStatus;
use App\Jobs\SendAttendanceConfirmation;
use App\Mail\AttendanceConfirmationMail;
use App\Models\AttendanceDelivery;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\User;
use App\Services\Events\EventImageCatalogueImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(EventImageCatalogueImporter::class)->replace();
});

it('creates one attendance and its durable delivery schedule', function () {
    Queue::fake();
    $user = User::factory()->create();
    $event = Event::factory()->published()->create([
        'starts_at' => now('UTC')->addDays(10),
        'ends_at' => now('UTC')->addDays(10)->addHours(2),
        'starts_on_local' => now('UTC')->addDays(10)->toDateString(),
    ]);

    $this->actingAs($user)
        ->put("/events/{$event->id}/attendance", ['intent' => 'going'])
        ->assertRedirect();

    $attendance = EventAttendance::query()->sole();
    expect($attendance->intent->value)->toBe('going')
        ->and($attendance->cancelled_at)->toBeNull()
        ->and($attendance->deliveries()->count())->toBe(3)
        ->and($attendance->deliveries()->where('status', DeliveryStatus::Pending->value)->count())->toBe(3);

    Queue::assertPushed(SendAttendanceConfirmation::class, fn ($job) => $job->attendanceId === $attendance->id && $job->attendanceRevision === 1
    );
});

it('updates an active intent without duplicating attendance or confirmation', function () {
    Queue::fake();
    $user = User::factory()->create();
    $event = Event::factory()->published()->create([
        'starts_at' => now('UTC')->addDays(10),
        'ends_at' => now('UTC')->addDays(10)->addHours(2),
        'starts_on_local' => now('UTC')->addDays(10)->toDateString(),
    ]);

    $this->actingAs($user)->put("/events/{$event->id}/attendance", ['intent' => 'interested']);
    $this->actingAs($user)->put("/events/{$event->id}/attendance", ['intent' => 'going']);

    expect(EventAttendance::query()->count())->toBe(1)
        ->and(EventAttendance::query()->sole()->intent->value)->toBe('going')
        ->and(AttendanceDelivery::query()->count())->toBe(3);
    Queue::assertPushed(SendAttendanceConfirmation::class, 1);
});

it('returns only the signed-in users intent for the event modal', function () {
    Queue::fake();
    $attendee = User::factory()->create();
    $otherUser = User::factory()->create();
    $event = Event::factory()->published()->create([
        'starts_at' => now('UTC')->addDays(10),
        'ends_at' => now('UTC')->addDays(10)->addHours(2),
        'starts_on_local' => now('UTC')->addDays(10)->toDateString(),
    ]);

    $this->actingAs($attendee)
        ->put("/events/{$event->id}/attendance", ['intent' => 'interested']);

    $this->actingAs($attendee)
        ->getJson("/events/{$event->id}/attendance/status")
        ->assertOk()
        ->assertExactJson(['intent' => 'interested']);

    $this->actingAs($otherUser)
        ->getJson("/events/{$event->id}/attendance/status")
        ->assertOk()
        ->assertExactJson(['intent' => null]);
});

it('cancels from the account and skips outstanding delivery work', function () {
    Queue::fake();
    $user = User::factory()->create();
    $event = Event::factory()->published()->create([
        'starts_at' => now('UTC')->addDays(10),
        'ends_at' => now('UTC')->addDays(10)->addHours(2),
        'starts_on_local' => now('UTC')->addDays(10)->toDateString(),
    ]);

    $this->actingAs($user)->put("/events/{$event->id}/attendance", ['intent' => 'going']);
    $this->actingAs($user)
        ->delete("/events/{$event->id}/attendance")
        ->assertRedirect();

    expect(EventAttendance::query()->sole()->cancelled_at)->not->toBeNull()
        ->and(AttendanceDelivery::query()->where('status', DeliveryStatus::Skipped->value)->count())->toBe(3);

    $this->actingAs($user)
        ->getJson("/events/{$event->id}/attendance/status")
        ->assertExactJson(['intent' => null]);
});

it('sends confirmation from the queued job and records delivery', function () {
    Queue::fake();
    Mail::fake();
    $user = User::factory()->create();
    $event = Event::factory()->published()->create([
        'starts_at' => now('UTC')->addDays(10),
        'ends_at' => now('UTC')->addDays(10)->addHours(2),
        'starts_on_local' => now('UTC')->addDays(10)->toDateString(),
    ]);

    $this->actingAs($user)->put("/events/{$event->id}/attendance", ['intent' => 'going']);
    $attendance = EventAttendance::query()->sole();

    (new SendAttendanceConfirmation($attendance->id, $attendance->revision))->handle();

    Mail::assertSent(AttendanceConfirmationMail::class, fn ($mail) => $mail->hasTo($user->email)
    );
    expect(AttendanceDelivery::query()
        ->where('kind', 'confirmation')
        ->firstOrFail(['status'])->status)->toBe(DeliveryStatus::Sent);
});

it('uses a signed confirmation page before cancelling from email', function () {
    Queue::fake();
    $user = User::factory()->create();
    $event = Event::factory()->published()->create([
        'starts_at' => now('UTC')->addDays(10),
        'ends_at' => now('UTC')->addDays(10)->addHours(2),
        'starts_on_local' => now('UTC')->addDays(10)->toDateString(),
    ]);
    $this->actingAs($user)->put("/events/{$event->id}/attendance", ['intent' => 'going']);
    $this->post('/logout');

    $attendance = EventAttendance::query()->sole();
    $url = URL::temporarySignedRoute(
        'attendance.cancel.confirm',
        now()->addDay(),
        ['attendance' => $attendance->id, 'revision' => $attendance->revision],
    );

    $this->get($url)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Attendance/Cancel')
            ->where('attendance.active', true));
    expect($attendance->fresh()->cancelled_at)->toBeNull();

    $this->delete($url)->assertRedirect($url);
    expect($attendance->fresh()->cancelled_at)->not->toBeNull();

    $this->get($url)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('attendance.active', false));

    $this->actingAs($user)->put("/events/{$event->id}/attendance", ['intent' => 'interested']);
    $this->get($url)->assertForbidden();
});

it('does not expose attendee email addresses on the public event detail', function () {
    Queue::fake();
    $attendee = User::factory()->create([
        'name' => 'Private Person',
        'email' => 'private-attendee@example.test',
    ]);
    $event = Event::factory()->published()->create([
        'starts_at' => now('UTC')->addDays(10),
        'ends_at' => now('UTC')->addDays(10)->addHours(2),
        'starts_on_local' => now('UTC')->addDays(10)->toDateString(),
    ]);
    $this->actingAs($attendee)->put("/events/{$event->id}/attendance", ['intent' => 'interested']);
    $this->post('/logout');

    $this->get("/events/{$event->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('attendance.counts.total', 1)
            ->where('attendance.attendees.0.name', 'Private P.')
            ->where('attendance.attendees.0.intent', 'interested'))
        ->assertDontSee('private-attendee@example.test');
});

it('keeps a cancelled event manageable without linking to its private page', function () {
    Queue::fake();
    $user = User::factory()->create();
    $event = Event::factory()->published()->create([
        'starts_at' => now('UTC')->addDays(10),
        'ends_at' => now('UTC')->addDays(10)->addHours(2),
        'starts_on_local' => now('UTC')->addDays(10)->toDateString(),
    ]);
    $this->actingAs($user)->put("/events/{$event->id}/attendance", ['intent' => 'going']);
    $event->update(['status' => 'cancelled']);

    $this->actingAs($user)
        ->get('/my-events')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('attendances.data.0.event.id', $event->id)
            ->where('attendances.data.0.event.status', 'cancelled'))
        ->assertDontSee("href=\"/events/{$event->id}\"", false);
});
