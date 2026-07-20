<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Attendance\DeliveryStatus;
use App\Domain\Events\EventStatus;
use App\Domain\Events\EventType;
use App\Http\Controllers\Controller;
use App\Models\AttendanceDelivery;
use App\Models\Event;
use App\Models\EventAttendance;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $statusCounts = $this->countsBy('status', EventStatus::values());
        $typeCounts = $this->countsBy('type', EventType::values());
        $activeAttendances = EventAttendance::query()->whereNull('cancelled_at')->count();
        $failedDeliveries = AttendanceDelivery::query()
            ->where('status', DeliveryStatus::Failed->value)
            ->count();

        return Inertia::render('Admin/Dashboard', [
            'summary' => [
                'total' => array_sum($statusCounts),
                'statuses' => $statusCounts,
                'types' => $typeCounts,
                'active_attendances' => $activeAttendances,
                'failed_deliveries' => $failedDeliveries,
            ],
        ]);
    }

    /**
     * @param  list<string>  $expectedValues
     * @return array<string, int>
     */
    private function countsBy(string $column, array $expectedValues): array
    {
        $counts = array_fill_keys($expectedValues, 0);

        /** @var Collection<int, object{bucket: string, aggregate: int|string}> $rows */
        $rows = $column === 'status'
            ? Event::query()->selectRaw('status AS bucket, COUNT(*) AS aggregate')->groupBy('status')->get()
            : Event::query()->selectRaw('type AS bucket, COUNT(*) AS aggregate')->groupBy('type')->get();

        foreach ($rows as $row) {
            if (isset($counts[$row->bucket])) {
                $counts[$row->bucket] = (int) $row->aggregate;
            }
        }

        return $counts;
    }
}
