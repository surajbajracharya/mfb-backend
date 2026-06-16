<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Services\PlanAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $courses = Course::with(['instructor:id,name,avatar', 'category:id,name,slug'])
            ->where('status', 'published')
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->when($request->category, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $request->category)))
            ->when($request->search, fn ($q) => $q->where('title', 'like', "%{$request->search}%"))
            ->when($request->level, fn ($q) => $q->where('level', $request->level))
            ->when($request->sort === 'latest', fn ($q) => $q->orderByDesc('id'), fn ($q) => $q->orderBy('sort_order')->orderBy('id'))
            ->withAvg('reviews', 'rating')
            ->withCount('enrollments')
            ->paginate($request->per_page ?? 12);

        return response()->json($courses);
    }

    public function show(string $slug): JsonResponse
    {
        $course = Course::with([
                'instructor:id,name,avatar,bio',
                'category:id,name,slug',
                'sections.lectures',
                'reviews.user:id,name,avatar',
            ])
            ->where('slug', $slug)
            ->where('status', 'published')
            ->withAvg('reviews', 'rating')
            ->withCount(['enrollments', 'reviews'])
            ->firstOrFail();

        // Hide content_url and resources from non-preview lectures
        $course->sections->each(function ($section) {
            $section->lectures->each(function ($lecture) {
                if (!$lecture->is_preview) {
                    $lecture->makeHidden(['content_url', 'resources']);
                }
            });
        });

        $isEnrolled    = false;
        $hasPlanAccess = false;
        $user = auth('sanctum')->user();
        if ($user) {
            $isEnrolled = Enrollment::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->exists();
            if (!$isEnrolled) {
                $hasPlanAccess = PlanAccessService::userHasAccess($user->id, 'course', $course->id);
            }
        }

        return response()->json(array_merge($course->toArray(), [
            'is_enrolled'     => $isEnrolled,
            'has_plan_access' => $hasPlanAccess,
        ]));
    }

    public function player(Request $request, string $slug): JsonResponse
    {
        $course = Course::with(['sections.lectures'])
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        $user = $request->user();

        // Check enrollment (direct purchase or previously plan-enrolled)
        $enrolled = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();

        // Fall back to active plan access — lets the user into the player but does NOT
        // enroll yet. Enrollment is created the first time they actually click a lecture
        // (via POST /courses/progress) — that's the real "showed interest" signal.
        $hasPlanAccess = !$enrolled && PlanAccessService::userHasAccess($user->id, 'course', $course->id);

        if (!$enrolled && !$hasPlanAccess && !$user->hasAnyRole(['admin', 'super_admin'])) {
            return response()->json(['message' => 'You are not enrolled in this course.'], 403);
        }

        // Get user progress for this course
        $lectureIds = $course->lectures()->pluck('course_lectures.id');
        $progress   = $user->progress()->whereIn('lecture_id', $lectureIds)->get()->keyBy('lecture_id');

        return response()->json([
            'course'   => $course,
            'progress' => $progress,
        ]);
    }
}