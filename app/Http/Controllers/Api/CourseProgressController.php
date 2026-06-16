<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CourseLecture;
use App\Models\CourseProgress;
use App\Models\Enrollment;
use App\Models\LectureNote;
use App\Services\PlanAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class CourseProgressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(["data" => $request->user()->courseProgress()->get()]);
    }
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            "lecture_id" => ["required", "exists:course_lectures,id"],
            "completed" => ["sometimes", "boolean"],
            "watched_seconds" => ["sometimes", "integer", "min:0"],
        ]);
        $user    = $request->user();
        $lecture = CourseLecture::with('section.course')->findOrFail($data['lecture_id']);
        $course  = $lecture->section->course ?? null;

        // If the user is accessing this course through an active plan subscription (no direct
        // enrollment yet), permanently enroll them now — clicking a lecture is the moment
        // they showed real interest, so they keep this course even after the plan expires.
        if ($course) {
            $alreadyEnrolled = Enrollment::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->exists();

            if (!$alreadyEnrolled && PlanAccessService::userHasAccess($user->id, 'course', $course->id)) {
                Enrollment::create([
                    'user_id'    => $user->id,
                    'course_id'  => $course->id,
                    'type'       => 'subscription',
                    'expires_at' => null,
                ]);
            }
        }

        $progress = CourseProgress::updateOrCreate(
            ["user_id" => $user->id, "lecture_id" => $data["lecture_id"]],
            array_merge($data, [
                "user_id"      => $user->id,
                "completed_at" => ($data["completed"] ?? false) ? now() : null,
            ])
        );

        // Auto-issue certificate when course is fully completed
        if (!empty($data['completed'])) {

            if ($course?->has_certificate) {
                $totalLectures = CourseLecture::whereHas('section', fn ($q) => $q->where('course_id', $course->id))->count();
                $completedLectures = CourseProgress::where('user_id', $user->id)
                    ->where('completed', true)
                    ->whereHas('lecture', fn ($q) => $q->whereHas('section', fn ($q2) => $q2->where('course_id', $course->id)))
                    ->count();

                if ($totalLectures > 0 && $completedLectures >= $totalLectures) {
                    $newCert = null;
                    DB::transaction(function () use ($user, $course, &$newCert) {
                        $today = now()->format('Y-m-d');
                        $count = Certificate::withoutCompanyScope()
                            ->whereDate('issued_at', $today)
                            ->lockForUpdate()
                            ->count();
                        $certNumber = $today . '-' . ($count + 1);
                        $cert = Certificate::firstOrCreate(
                            ['user_id' => $user->id, 'course_id' => $course->id],
                            ['certificate_number' => $certNumber, 'issued_at' => now(), 'company_id' => $user->company_id ?? null]
                        );
                        if ($cert->wasRecentlyCreated) {
                            $newCert = $cert;
                        }
                    });

                    if ($newCert) {
                        $company     = app(\App\Services\TenantContext::class)->getCompany();
                        $scheme      = app()->environment('local') ? 'http' : 'https';
                        $frontendUrl = $company ? rtrim($scheme . '://' . $company->domain, '/') : rtrim(config('app.frontend_url'), '/');
                        \App\Services\EmailService::send($user->email, 'certificate_issued', [
                            '{username}'           => $user->name,
                            '{email}'              => $user->email,
                            '{course_title}'       => $course->title,
                            '{certificate_number}' => $newCert->certificate_number,
                            '{certificate_url}'    => $frontendUrl . '/dashboard/certificates',
                            '{site_name}'          => \App\Models\Setting::getValue('site_name', config('app.name')),
                        ]);
                    }
                }
            }
        }

        return response()->json($progress);
    }
    public function courseProgress(Request $request, string $courseId): JsonResponse
    {
        $progress = $request->user()->courseProgress()
            ->whereHas("lecture", fn ($q) => $q->whereHas("section", fn ($q2) => $q2->where("course_id", $courseId)))
            ->get()->keyBy("lecture_id");
        return response()->json(["data" => $progress]);
    }
    public function notes(Request $request): JsonResponse
    {
        return response()->json(["data" => LectureNote::where("user_id", $request->user()->id)->latest()->get()]);
    }
    public function storeNote(Request $request, string $lectureId): JsonResponse
    {
        $lecture  = CourseLecture::with('section:id,course_id')->findOrFail($lectureId);
        $courseId = $lecture->section->course_id;
        $userId   = $request->user()->id;

        $enrolled = Enrollment::where('user_id', $userId)->where('course_id', $courseId)->exists()
            || PlanAccessService::userHasAccess($userId, 'course', $courseId);

        if (!$enrolled) abort(403, 'You are not enrolled in this course.');

        $note = LectureNote::create([
            "user_id"    => $userId,
            "lecture_id" => $lectureId,
            "content"    => $request->validate(["content" => ["required", "string"]])["content"],
        ]);
        return response()->json($note, 201);
    }
    public function updateNote(Request $request, string $id): JsonResponse
    {
        $note = LectureNote::where("user_id", $request->user()->id)->findOrFail($id);
        $note->update($request->validate(["content" => ["required", "string"]]));
        return response()->json($note);
    }
    public function deleteNote(Request $request, string $id): JsonResponse
    {
        LectureNote::where("user_id", $request->user()->id)->findOrFail($id)->delete();
        return response()->json(["message" => "Note deleted."]);
    }
}