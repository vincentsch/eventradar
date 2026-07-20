<?php

namespace App\Http\Controllers;

use App\Models\EventAttendance;
use App\Services\Attendance\AttendanceManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SignedAttendanceController extends Controller
{
    public function confirm(Request $request, EventAttendance $attendance): Response
    {
        $revision = $request->integer('revision');
        // Let a just-used link show its cancelled state, but never let an old
        // email manage a later reactivation of the same attendance record.
        abort_unless(
            $revision === $attendance->revision
            || ($attendance->cancelled_at !== null && $revision === $attendance->revision - 1),
            403,
        );
        $attendance->load(['event:id,title,starts_at,timezone,venue_name,formatted_address,locality,country']);

        return Inertia::render('Attendance/Cancel', [
            'attendance' => [
                'active' => $attendance->cancelled_at === null,
                'event' => [
                    'title' => $attendance->event->title,
                    'starts_at' => $attendance->event->starts_at->toISOString(),
                    'timezone' => $attendance->event->timezone,
                    'location' => $attendance->event->formatted_address
                        ? implode(', ', [$attendance->event->venue_name, $attendance->event->formatted_address])
                        : implode(', ', array_filter([
                            $attendance->event->venue_name,
                            $attendance->event->locality,
                            $attendance->event->country,
                        ])),
                ],
            ],
        ]);
    }

    public function destroy(
        Request $request,
        EventAttendance $attendance,
        AttendanceManager $manager,
    ): RedirectResponse {
        abort_unless($request->integer('revision') === $attendance->revision, 403);
        $manager->cancel($attendance);

        return redirect()->to($request->fullUrl());
    }
}
