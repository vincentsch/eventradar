<?php

namespace App\Mail;

use App\Models\EventAttendance;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class AttendanceConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var array<string, string> */
    public readonly array $eventDetails;

    public readonly string $manageUrl;

    public readonly string $cancelUrl;

    public function __construct(public readonly EventAttendance $attendance)
    {
        $event = $attendance->event;
        $startsAt = $event->starts_at->setTimezone($event->timezone);

        $this->eventDetails = [
            'title' => $event->title,
            'intent' => $attendance->intent->value,
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
            $event->ends_at,
            ['attendance' => $attendance->id],
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You are on the list for '.$this->eventDetails['title'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.attendance-confirmation',
            text: 'mail.attendance-confirmation-text',
        );
    }
}
