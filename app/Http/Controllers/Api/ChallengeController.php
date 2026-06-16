<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Challenge;
use App\Models\ChallengeDay;
use App\Models\ChallengeEnrollment;
use App\Models\ChallengeDayProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChallengeController extends Controller
{
    // ── Public list ──────────────────────────────────────────────────────────
    public function index(): JsonResponse
    {
        $challenges = Challenge::where('status', 'published')
            ->withCount('enrollments')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json($challenges);
    }

    // ── Public detail ─────────────────────────────────────────────────────────
    public function show(Request $request, string $slug): JsonResponse
    {
        $challenge = Challenge::where('slug', $slug)
            ->where('status', 'published')
            ->withCount('enrollments')
            ->with('days')
            ->firstOrFail();

        $result = $challenge->toArray();
        $result['enrollment'] = null;
        $result['completed_day_ids'] = [];

        if ($user = auth('sanctum')->user()) {
            $enrollment = ChallengeEnrollment::where('user_id', $user->id)
                ->where('challenge_id', $challenge->id)
                ->first();

            if ($enrollment) {
                $progress = ChallengeDayProgress::where('user_id', $user->id)
                    ->whereIn('challenge_day_id', $challenge->days->pluck('id'))
                    ->get();

                $completed = $progress->whereNotNull('completed_at');
                $result['enrollment']        = $enrollment;
                $result['completed_day_ids'] = $completed->pluck('challenge_day_id')->toArray();
                $result['day_moods']         = $progress->pluck('mood', 'challenge_day_id')->toArray();
                $result['day_completions']   = $completed->pluck('completed_at', 'challenge_day_id')->toArray();
            }
        }

        return response()->json($result);
    }

    // ── Enroll ────────────────────────────────────────────────────────────────
    public function enroll(Request $request, string $id): JsonResponse
    {
        $challenge = Challenge::where('status', 'published')->findOrFail($id);

        $enrollment = ChallengeEnrollment::firstOrCreate(
            ['user_id' => $request->user()->id, 'challenge_id' => $challenge->id],
            [
                'company_id'  => $challenge->company_id,
                'enrolled_at' => now(),
            ]
        );

        return response()->json($enrollment, $enrollment->wasRecentlyCreated ? 201 : 200);
    }

    // ── Save mood ─────────────────────────────────────────────────────────────
    public function saveMood(Request $request, string $challengeId, string $dayId): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate(['mood' => ['required', 'integer', 'min:1', 'max:5']]);

        $progress = ChallengeDayProgress::updateOrCreate(
            ['user_id' => $user->id, 'challenge_day_id' => $dayId],
            ['mood' => $data['mood']]
        );

        return response()->json(['mood' => $progress->mood]);
    }

    // ── My enrollments ────────────────────────────────────────────────────────
    public function myEnrollments(Request $request): JsonResponse
    {
        $enrollments = ChallengeEnrollment::where('user_id', $request->user()->id)
            ->with('challenge')
            ->latest()
            ->get()
            ->map(function ($enrollment) use ($request) {
                $challenge = $enrollment->challenge;
                $totalDays = $challenge->total_days;

                $completedCount = ChallengeDayProgress::where('user_id', $request->user()->id)
                    ->whereHas('day', fn($q) => $q->where('challenge_id', $challenge->id))
                    ->count();

                return array_merge($enrollment->toArray(), [
                    'completed_days' => $completedCount,
                    'total_days'     => $totalDays,
                    'progress_percent' => $totalDays > 0
                        ? round(($completedCount / $totalDays) * 100)
                        : 0,
                ]);
            });

        return response()->json($enrollments);
    }

    // ── Complete a day ────────────────────────────────────────────────────────
    public function completeDay(Request $request, string $challengeId, string $dayId): JsonResponse
    {
        $user = $request->user();

        $enrollment = ChallengeEnrollment::where('user_id', $user->id)
            ->where('challenge_id', $challengeId)
            ->firstOrFail();

        $day = ChallengeDay::where('challenge_id', $challengeId)->findOrFail($dayId);

        // Sequential unlock: day N requires day N-1 to be complete
        if ($day->day_number > 1) {
            $prevDay = ChallengeDay::where('challenge_id', $challengeId)
                ->where('day_number', $day->day_number - 1)
                ->first();

            if ($prevDay) {
                $prevProgress = ChallengeDayProgress::where('user_id', $user->id)
                    ->where('challenge_day_id', $prevDay->id)
                    ->whereNotNull('completed_at')
                    ->first();

                if (!$prevProgress) {
                    return response()->json(
                        ['message' => 'Complete the previous day first.'],
                        422
                    );
                }

                // Must wait until the next calendar day after completing the previous day
                if ($prevProgress->completed_at->isToday()) {
                    return response()->json(
                        ['message' => 'You can unlock the next day tomorrow. Come back then!'],
                        422
                    );
                }
            }
        }

        // firstOrNew so we can set completed_at even if record already exists
        // from saveMood (which creates the record without completed_at)
        $dp = ChallengeDayProgress::firstOrNew(
            ['user_id' => $user->id, 'challenge_day_id' => $day->id]
        );
        if (!$dp->completed_at) {
            $dp->completed_at = now();
            $dp->save();
        }

        // Check if all days are now complete
        $challenge = $enrollment->challenge;
        $totalDays = ChallengeDay::where('challenge_id', $challengeId)->count();
        $completedCount = ChallengeDayProgress::where('user_id', $user->id)
            ->whereHas('day', fn($q) => $q->where('challenge_id', $challengeId))
            ->count();

        if ($completedCount >= $totalDays && !$enrollment->completed_at) {
            $enrollment->update([
                'completed_at'  => now(),
                'badge_awarded' => true,
            ]);
        }

        return response()->json([
            'completed_days'   => $completedCount,
            'total_days'       => $totalDays,
            'progress_percent' => $totalDays > 0 ? round(($completedCount / $totalDays) * 100) : 0,
            'badge_awarded'    => $enrollment->fresh()->badge_awarded,
        ]);
    }
}
