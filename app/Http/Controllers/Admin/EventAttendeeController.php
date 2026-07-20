<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Attendance\AttendanceIntent;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventAttendance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventAttendeeController extends Controller
{
    public function __invoke(Request $request, string $event): Response
    {
        $record = Event::query()->findOrFail($event, ['id', 'title']);
        $intent = $request->string('intent')->trim()->toString();
        $state = $request->string('state')->trim()->toString();
        $query = $request->string('q')->trim()->toString();

        $intent = in_array($intent, AttendanceIntent::values(), true) ? $intent : null;
        $state = in_array($state, ['active', 'cancelled'], true) ? $state : null;
        $query = mb_strlen($query) >= 2 && mb_strlen($query) <= 100 ? $query : null;

        $attendees = EventAttendance::query()
            ->select([
                'event_attendances.id',
                'event_attendances.intent',
                'event_attendances.cancelled_at',
                'event_attendances.created_at',
                'event_attendances.updated_at',
                'users.name',
                'users.email',
            ])
            ->join('users', 'users.id', '=', 'event_attendances.user_id')
            ->where('event_attendances.event_id', $record->id)
            ->when($intent !== null, fn (Builder $builder) => $builder->where('event_attendances.intent', $intent))
            ->when($state === 'active', fn (Builder $builder) => $builder->whereNull('event_attendances.cancelled_at'))
            ->when($state === 'cancelled', fn (Builder $builder) => $builder->whereNotNull('event_attendances.cancelled_at'))
            ->when($query !== null, function (Builder $builder) use ($query): void {
                $builder->where(function (Builder $builder) use ($query): void {
                    $builder->where('users.name', 'like', $query.'%')
                        ->orWhere('users.email', 'like', $query.'%');
                });
            })
            ->orderByDesc('event_attendances.created_at')
            ->orderByDesc('event_attendances.id')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Admin/Events/Attendees', [
            'event' => $record->only(['id', 'title']),
            'attendees' => $attendees,
            'filters' => [
                'q' => $query,
                'intent' => $intent,
                'state' => $state,
            ],
            'options' => [
                'intents' => AttendanceIntent::values(),
            ],
        ]);
    }
}
