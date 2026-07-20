<?php

namespace App\Http\Controllers;

use App\Domain\Attendance\AttendanceIntent;
use App\Http\Requests\StoreAttendanceRequest;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Services\Attendance\AttendanceManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AttendanceController extends Controller
{
    public function begin(string $event): RedirectResponse
    {
        return redirect()->route('events.show', ['event' => $event]);
    }

    /**
     * The viewer's current intent for one event, read lazily by the event
     * detail modal when it opens.
     */
    public function status(Request $request, string $event): JsonResponse
    {
        $intent = EventAttendance::query()
            ->where('event_id', $event)
            ->where('user_id', $request->user()->id)
            ->whereNull('cancelled_at')
            ->first(['intent'])
            ?->getAttribute('intent');

        return response()->json([
            'intent' => $intent instanceof AttendanceIntent ? $intent->value : $intent,
        ]);
    }

    public function store(
        StoreAttendanceRequest $request,
        string $event,
        AttendanceManager $manager,
    ): RedirectResponse {
        $record = Event::query()->findOrFail($event, ['id', 'status', 'starts_at', 'ends_at']);
        $attendance = $manager->register($request->user(), $record, $request->intent());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $attendance->wasRecentlyCreated
                ? 'You are on the list. A confirmation email is on its way.'
                : 'Your event preference has been updated.',
        ]);

        return back();
    }

    public function destroy(Request $request, string $event, AttendanceManager $manager): RedirectResponse
    {
        $attendance = EventAttendance::query()
            ->where('event_id', $event)
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
