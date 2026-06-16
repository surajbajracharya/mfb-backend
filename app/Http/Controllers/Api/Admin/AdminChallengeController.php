<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Challenge;
use App\Models\ChallengeDay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminChallengeController extends Controller
{
    // ── List ─────────────────────────────────────────────────────────────────
    public function index(): JsonResponse
    {
        $challenges = Challenge::withCount('enrollments')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate(50);

        return response()->json($challenges);
    }

    // ── Detail (with days) ───────────────────────────────────────────────────
    public function show(string $id): JsonResponse
    {
        return response()->json(
            Challenge::with('days')->withCount('enrollments')->findOrFail($id)
        );
    }

    // ── Create ───────────────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'             => ['required', 'string', 'max:255'],
            'slug'              => ['required', 'string', 'unique:challenges,slug', 'max:255'],
            'short_description' => ['nullable', 'string'],
            'description'       => ['nullable', 'string'],
            'thumbnail'         => ['nullable', 'string', 'max:500'],
            'badge_image'       => ['nullable', 'string', 'max:500'],
            'total_days'        => ['required', 'integer', 'min:1', 'max:365'],
            'status'            => ['sometimes', 'in:draft,published'],
            'meta_title'        => ['nullable', 'string', 'max:120'],
            'meta_description'  => ['nullable', 'string', 'max:320'],
            'og_image'          => ['nullable', 'string', 'max:500'],
            'schema_markup'     => ['nullable', 'string'],
            'robots'            => ['nullable', 'string'],
        ]);

        $data['user_id'] = $request->user()->id;

        // Push all existing challenges down so new one sits at top
        Challenge::query()->increment('sort_order');
        $data['sort_order'] = 0;

        return response()->json(Challenge::create($data), 201);
    }

    // ── Update ───────────────────────────────────────────────────────────────
    public function update(Request $request, string $id): JsonResponse
    {
        $challenge = Challenge::findOrFail($id);

        $data = $request->validate([
            'title'             => ['sometimes', 'string', 'max:255'],
            'slug'              => ['sometimes', 'string', 'unique:challenges,slug,' . $id, 'max:255'],
            'short_description' => ['nullable', 'string'],
            'description'       => ['nullable', 'string'],
            'thumbnail'         => ['nullable', 'string', 'max:500'],
            'badge_image'       => ['nullable', 'string', 'max:500'],
            'total_days'        => ['sometimes', 'integer', 'min:1', 'max:365'],
            'status'            => ['sometimes', 'in:draft,published'],
            'sort_order'        => ['sometimes', 'integer', 'min:0'],
            'meta_title'        => ['nullable', 'string', 'max:120'],
            'meta_description'  => ['nullable', 'string', 'max:320'],
            'og_image'          => ['nullable', 'string', 'max:500'],
            'schema_markup'     => ['nullable', 'string'],
            'robots'            => ['nullable', 'string'],
        ]);

        $challenge->update($data);

        return response()->json($challenge->fresh('days'));
    }

    // ── Delete ───────────────────────────────────────────────────────────────
    public function destroy(string $id): JsonResponse
    {
        Challenge::findOrFail($id)->delete();
        return response()->json(['message' => 'Challenge deleted.']);
    }

    // ── Reorder ──────────────────────────────────────────────────────────────
    public function reorder(Request $request): JsonResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array']])['ids'];
        foreach ($ids as $index => $id) {
            Challenge::where('id', $id)->update(['sort_order' => $index]);
        }
        return response()->json(['message' => 'Order saved.']);
    }

    // ── Publish / Unpublish ──────────────────────────────────────────────────
    public function publish(string $id): JsonResponse
    {
        $c = Challenge::findOrFail($id);
        $c->update(['status' => 'published']);
        return response()->json($c);
    }

    public function unpublish(string $id): JsonResponse
    {
        $c = Challenge::findOrFail($id);
        $c->update(['status' => 'draft']);
        return response()->json($c);
    }

    // ── Days ─────────────────────────────────────────────────────────────────
    public function getDays(string $challengeId): JsonResponse
    {
        $challenge = Challenge::findOrFail($challengeId);
        return response()->json($challenge->days);
    }

    public function storeDay(Request $request, string $challengeId): JsonResponse
    {
        $challenge = Challenge::findOrFail($challengeId);

        $data = $request->validate([
            'day_number'        => ['required', 'integer', 'min:1'],
            'title'             => ['required', 'string', 'max:255'],
            'instructions'      => ['nullable', 'string'],
            'video_url'         => ['nullable', 'string', 'max:500'],
            'audio_url'         => ['nullable', 'string', 'max:500'],
            'image_url'         => ['nullable', 'string', 'max:500'],
            'duration_minutes'  => ['sometimes', 'integer', 'min:0'],
        ]);

        $data['challenge_id'] = $challenge->id;

        $day = ChallengeDay::updateOrCreate(
            ['challenge_id' => $challenge->id, 'day_number' => $data['day_number']],
            $data
        );

        return response()->json($day, 201);
    }

    public function updateDay(Request $request, string $challengeId, string $dayId): JsonResponse
    {
        $day = ChallengeDay::where('challenge_id', $challengeId)->findOrFail($dayId);

        $data = $request->validate([
            'day_number'       => ['sometimes', 'integer', 'min:1'],
            'title'            => ['sometimes', 'string', 'max:255'],
            'instructions'     => ['nullable', 'string'],
            'video_url'        => ['nullable', 'string', 'max:500'],
            'audio_url'        => ['nullable', 'string', 'max:500'],
            'image_url'        => ['nullable', 'string', 'max:500'],
            'duration_minutes' => ['sometimes', 'integer', 'min:0'],
        ]);

        $day->update($data);

        return response()->json($day);
    }

    public function destroyDay(string $challengeId, string $dayId): JsonResponse
    {
        ChallengeDay::where('challenge_id', $challengeId)->findOrFail($dayId)->delete();
        return response()->json(['message' => 'Day deleted.']);
    }

    public function reorderDays(Request $request, string $challengeId): JsonResponse
    {
        Challenge::findOrFail($challengeId);
        $ids = $request->validate(['ids' => ['required', 'array']])['ids'];
        foreach ($ids as $index => $id) {
            ChallengeDay::where('challenge_id', $challengeId)->where('id', $id)
                ->update(['day_number' => $index + 1]);
        }
        return response()->json(['message' => 'Days reordered.']);
    }
}
