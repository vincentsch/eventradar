<?php

namespace App\Mail;

use App\Domain\Attendance\DeliveryKind;
use App\Models\EventAttendance;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class EventReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var array<string, string> */
    public readonly array $eventDetails;

    public readonly string $eyebrow;

    public readonly string $heading;

    public readonly string $manageUrl;

    public readonly string $cancelUrl;

    public function __construct(
        public readonly EventAttendance $attendance,
        public readonly DeliveryKind $kind,
    ) {
        $event = $attendance->event;
        $startsAt = $event->starts_at->setTimezone($event->timezone);

        $this->eyebrow = $kind === DeliveryKind::ThreeDays ? 'Three-day reminder' : 'Tomorrow';
        $this->heading = $kind === DeliveryKind::ThreeDays
            ? 'Your event is getting close.'
            : 'Your event starts in 24 hours.';
        $this->eventDetails = [
            'title' => $event->title,
            'date' => $startsAt->format('l, F j, Y'),
            'time' => $startsAt->format('H:i T'),
            'timezone' => $event->timezone,
            'location' => $event->formatted_address
                ? implode(', ', [$event->venue_name, $event->formatted_address])
                : implode(', ', array_filter([
                    $event->venue_name,
                    $event->locality,
                    $event->region,
                    $event->country,
                ])),
            'event_url' => route('events.show', $event),
        ];
        $this->manageUrl = route('account.events.index');
        $this->cancelUrl = URL::temporarySignedRoute(
            'attendance.cancel.confirm',
            now()->addDays(7),
            ['attendance' => $attendance->id, 'revision' => $attendance->revision],
        );
    }

    public function envelope(): Envelope
    {
        $prefix = $this->kind === DeliveryKind::ThreeDays ? 'Coming up' : 'Tomorrow';

        return new Envelope(subject: $prefix.': '.$this->eventDetails['title']);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.event-reminder',
            text: 'mail.event-reminder-text',
        );
    }
}
