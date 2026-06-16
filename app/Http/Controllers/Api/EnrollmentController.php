<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Services\EmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class EnrollmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $enrollments = $user
            ->enrollments()
            ->with(["course:id,title,slug,thumbnail,type,level,language,has_certificate"])
            ->where(fn ($q) => $q->whereNull("expires_at")->orWhere("expires_at", ">", now()))
            ->latest()
            ->get();

        // Attach per-course progress stats
        $enrollments->each(function ($enrollment) use ($user) {
            $course = $enrollment->course;
            if (!$course) return;

            $totalLectures = \App\Models\CourseLecture::whereHas(
                'section', fn ($q) => $q->where('course_id', $course->id)
            )->count();

            $completedLectures = \App\Models\CourseProgress::where('user_id', $user->id)
                ->where('completed', true)
                ->whereHas('lecture', fn ($q) => $q->whereHas(
                    'section', fn ($q2) => $q2->where('course_id', $course->id)
                ))->count();

            $enrollment->setAttribute('total_lectures', $totalLectures);
            $enrollment->setAttribute('completed_lectures', $completedLectures);
            $enrollment->setAttribute('progress_percent',
                $totalLectures > 0 ? (int) round($completedLectures / $totalLectures * 100) : 0
            );
        });

        return response()->json(["data" => $enrollments]);
    }
    public function show(Request $request, string $id): JsonResponse
    {
        $enrollment = $request->user()->enrollments()->with("course")->findOrFail($id);
        return response()->json($enrollment);
    }

    public function enrollFree(Request $request, string $id): JsonResponse
    {
        $course = Course::where('status', 'published')->where('type', 'free')->findOrFail($id);
        $user   = $request->user();

        $enrollment = Enrollment::firstOrCreate(
            ['user_id' => $user->id, 'course_id' => $course->id],
            ['type' => 'purchased']
        );

        if ($enrollment->wasRecentlyCreated) {
            $company     = app(\App\Services\TenantContext::class)->getCompany();
            $scheme      = app()->environment('local') ? 'http' : 'https';
            $frontendUrl = $company ? rtrim($scheme . '://' . $company->domain, '/') : rtrim(config('app.frontend_url'), '/');
            EmailService::send($user->email, 'enrollment_confirmed', [
                '{username}'      => $user->name,
                '{email}'         => $user->email,
                '{course_title}'  => $course->title,
                '{course_url}'    => $frontendUrl . '/courses/' . $course->slug,
                '{dashboard_url}' => $frontendUrl . '/dashboard',
                '{site_name}'     => Setting::getValue('site_name', config('app.name')),
            ]);
        }

        return response()->json([
            'message'    => 'You are now enrolled!',
            'enrollment' => $enrollment,
        ], 201);
    }
}