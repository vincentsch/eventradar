<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttendanceRequest;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Services\Attendance\AttendanceManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AttendanceController extends Controller
{
    public function begin(Event $event): RedirectResponse
    {
        return redirect()->route('events.show', $event);
    }

    public function store(
        StoreAttendanceRequest $request,
        Event $event,
        AttendanceManager $manager,
    ): RedirectResponse {
        $attendance = $manager->register($request->user(), $event, $request->intent());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $attendance->wasRecentlyCreated
                ? 'You are on the list. A confirmation email is on its way.'
                : 'Your event preference has been updated.',
        ]);

        return back();
    }

    public function destroy(Request $request, Event $event, AttendanceManager $manager): RedirectResponse
    {
        $attendance = EventAttendance::query()
            ->where('event_id', $event->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $manager->cancel($attendance);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'You are no longer on this event list.',
        ]);

        return back();
    }
}
