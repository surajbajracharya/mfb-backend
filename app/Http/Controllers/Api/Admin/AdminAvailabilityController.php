<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AvailabilitySchedule;
use App\Models\BlockedDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAvailabilityController extends Controller
{
    // GET /admin/availability?month=2026-03  OR  ?date=2026-03-04
    public function index(Request $request): JsonResponse
    {
        $query = AvailabilitySchedule::query()->orderBy('date')->orderBy('start_time');

        if ($request->filled('date')) {
            $query->forDate($request->date);
        } elseif ($request->filled('month')) {
            $query->forMonth($request->month);
        }

        return response()->json($query->get());
    }

    // POST /admin/availability
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date'       => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time'   => ['required', 'date_format:H:i', 'after:start_time'],
        ]);

        // Normalize times to H:i:s
        $data['start_time'] = $data['start_time'] . ':00';
        $data['end_time']   = $data['end_time'] . ':00';

        // Check for overlapping windows on the same date
        $overlap = AvailabilitySchedule::forDate($data['date'])
            ->where(function ($q) use ($data) {
                $q->where('start_time', '<', $data['end_time'])
                  ->where('end_time', '>', $data['start_time']);
            })->exists();

        if ($overlap) {
            return response()->json(['message' => 'This time window overlaps with an existing one.'], 422);
        }

        $schedule = AvailabilitySchedule::create($data);

        // Adding a window to a blocked day unblocks it
        BlockedDate::where('date', $data['date'])->delete();

        return response()->json($schedule, 201);
    }

    // PUT /admin/availability/{id}
    public function update(Request $request, string $id): JsonResponse
    {
        $schedule = AvailabilitySchedule::findOrFail($id);

        $data = $request->validate([
            'date'       => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time'   => ['required', 'date_format:H:i', 'after:start_time'],
        ]);

        $data['start_time'] = $data['start_time'] . ':00';
        $data['end_time']   = $data['end_time'] . ':00';

        // Check overlap excluding self
        $overlap = AvailabilitySchedule::forDate($data['date'])
            ->where('id', '!=', $id)
            ->where(function ($q) use ($data) {
                $q->where('start_time', '<', $data['end_time'])
                  ->where('end_time', '>', $data['start_time']);
            })->exists();

        if ($overlap) {
            return response()->json(['message' => 'This time window overlaps with an existing one.'], 422);
        }

        $schedule->update($data);
        return response()->json($schedule);
    }

    // GET /admin/availability/extent?start_time=08:00:00&end_time=19:00:00&from_date=2026-04-15
    // Returns the last date and total count for this window pattern from from_date onwards
    public function extent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'start_time' => ['required'],
            'end_time'   => ['required'],
            'from_date'  => ['required', 'date_format:Y-m-d'],
        ]);

        $result = AvailabilitySchedule::where('start_time', $data['start_time'])
            ->where('end_time', $data['end_time'])
            ->where('date', '>=', $data['from_date'])
            ->selectRaw('MAX(date) as last_date, COUNT(*) as total')
            ->first();

        return response()->json([
            'last_date' => $result->last_date,
            'total'     => (int) $result->total,
        ]);
    }

    // DELETE /admin/availability/{id}
    public function destroy(string $id): JsonResponse
    {
        AvailabilitySchedule::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    // DELETE /admin/availability/day  — clears all windows for a single date and marks it blocked
    public function destroyDay(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $count = AvailabilitySchedule::forDate($data['date'])->delete();

        // Record as explicitly blocked so the calendar can show a red dot
        BlockedDate::firstOrCreate(['date' => $data['date']]);

        return response()->json(['message' => "Cleared {$count} window(s) for {$data['date']}.", 'count' => $count]);
    }

    // GET /admin/availability/blocked-dates?month=2026-04
    public function blockedDates(Request $request): JsonResponse
    {
        $request->validate(['month' => ['required']]);

        $dates = BlockedDate::forMonth($request->month)->pluck('date');

        return response()->json($dates);
    }

    // DELETE /admin/availability/bulk
    // Deletes all windows from from_date onwards that share the same start_time + end_time
    public function bulkDestroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_date'  => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i:s'],
            'end_time'   => ['required', 'date_format:H:i:s'],
        ]);

        $count = AvailabilitySchedule::where('date', '>=', $data['from_date'])
            ->where('start_time', $data['start_time'])
            ->where('end_time', $data['end_time'])
            ->delete();

        return response()->json(['message' => "Deleted {$count} window(s).", 'count' => $count]);
    }
}
