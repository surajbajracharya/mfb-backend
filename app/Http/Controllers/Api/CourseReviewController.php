<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseReview;
use App\Models\User;
use App\Services\EmailService;
use App\Traits\HasTrash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class CourseReviewController extends Controller
{
    use HasTrash;
    protected string $model = CourseReview::class;
    protected array $trashedWith = ['user:id,name,avatar', 'course:id,title,slug', 'company:id,name'];
    public function index(Request $request, string $courseId): JsonResponse
    {
        return response()->json(CourseReview::where("course_id", $courseId)->where("is_approved", true)->with("user:id,name,avatar")->latest()->paginate(20));
    }
    public function store(Request $request, string $courseId): JsonResponse
    {
        $userId = $request->user()->id;

        // Block duplicate: one review per user per course
        if (CourseReview::where("user_id", $userId)->where("course_id", $courseId)->exists()) {
            return response()->json(["message" => "You have already submitted a review for this course. You may edit your existing review from the course page."], 422);
        }

        // Daily rate limit: max 10 new reviews per day
        $todayCount = CourseReview::where("user_id", $userId)
            ->whereDate("created_at", today())
            ->count();
        if ($todayCount >= 10) {
            return response()->json(["message" => "Thank you for your enthusiasm! You've shared 10 reviews today, which is our daily limit. Please come back tomorrow to share more of your thoughts."], 429);
        }

        $data = $request->validate([
            "rating"  => ["required", "integer", "min:1", "max:5"],
            "comment" => ["nullable", "string"],
        ]);

        $review = CourseReview::create(
            array_merge($data, ["user_id" => $userId, "course_id" => $courseId, "is_approved" => false])
        );

        $this->sendReviewEmails($request->user(), $courseId, $data);

        return response()->json($review, 201);
    }

    private function sendReviewEmails(User $user, string $courseId, array $data): void
    {
        $course = Course::find($courseId);
        if (!$course) return;

        $courseUrl  = config('app.frontend_url', config('app.url')) . '/courses/' . $course->slug;
        $adminUrl   = config('app.admin_url', config('app.url')) . '/admin/reviews';
        $stars      = str_repeat('★', (int) $data['rating']) . str_repeat('☆', 5 - (int) $data['rating']);
        $comment    = $data['comment'] ?? '(no comment)';
        $shortcodes = [
            '{username}'          => $user->name,
            '{email}'             => $user->email,
            '{course_title}'      => $course->title,
            '{course_url}'        => $courseUrl,
            '{rating}'            => $data['rating'] . '/5 ' . $stars,
            '{comment}'           => $comment,
            '{admin_reviews_url}' => $adminUrl,
            '{site_name}'         => AppModelsSetting::getValue('site_name', config('app.name')),
        ];

        EmailService::send($user->email, 'review_submitted', $shortcodes);

        foreach ($this->getAdminEmailsForNotification($course->company_id, 'moderate reviews') as $email) {
            EmailService::send($email, 'review_received_admin', $shortcodes);
        }
    }

    /**
     * Returns emails of all admins who should receive this module notification.
     * Global admins (super_admin / admin roles) always get notified.
     * Company-scoped admins only if they have the given permission.
     */
    private function getAdminEmailsForNotification(?int $companyId, string $permission): array
    {
        // Global roles get everything
        $emails = User::role(['super_admin', 'admin'])->pluck('email');

        // Company-scoped admins who have the specific permission
        if ($companyId) {
            $emails = $emails->merge(
                User::permission($permission)
                    ->where('company_id', $companyId)
                    ->pluck('email')
            );
        }

        return $emails->unique()->filter()->values()->all();
    }
    public function update(Request $request, string $courseId, string $id): JsonResponse
    {
        $review = CourseReview::where("user_id", $request->user()->id)->findOrFail($id);
        $data   = $request->validate([
            "rating"  => ["sometimes", "integer", "min:1", "max:5"],
            "comment" => ["nullable", "string"],
        ]);
        $review->update(array_merge($data, ["is_approved" => false]));
        return response()->json($review);
    }
    public function destroy(Request $request, string $courseId, string $id): JsonResponse
    {
        CourseReview::where("user_id", $request->user()->id)->findOrFail($id)->delete();
        return response()->json(["message" => "Review deleted."]);
    }

    // Admin methods
    public function adminIndex(Request $request, string $courseId): JsonResponse
    {
        $reviews = CourseReview::where("course_id", $courseId)
            ->with("user:id,name,avatar,email")
            ->latest()
            ->get();
        return response()->json($reviews);
    }

    public function approve(Request $request, string $courseId, string $id): JsonResponse
    {
        $review = CourseReview::where("course_id", $courseId)->with(['user', 'course'])->findOrFail($id);
        $review->update(["is_approved" => true]);
        $this->sendApprovalEmail($review);
        return response()->json($review);
    }

    public function unapprove(Request $request, string $courseId, string $id): JsonResponse
    {
        $review = CourseReview::where("course_id", $courseId)->with(['user', 'course'])->findOrFail($id);
        $review->update(["is_approved" => false]);
        $this->sendUnapprovalEmail($review);
        return response()->json($review);
    }

    public function adminDestroy(Request $request, string $courseId, string $id): JsonResponse
    {
        $review = CourseReview::where("course_id", $courseId)->with(['user', 'course'])->findOrFail($id);
        $this->sendDeletedEmail($review);
        $review->delete();
        return response()->json(["message" => "Review deleted."]);
    }

    // Global admin methods (all courses)
    public function adminAllReviews(Request $request): JsonResponse
    {
        $query = CourseReview::with(["user:id,name,avatar,email", "course:id,title,slug", "company:id,name"])->latest();
        if ($request->filled("course_id")) {
            $query->where("course_id", $request->course_id);
        }
        if ($request->filled("status")) {
            $query->where("is_approved", $request->status === "approved");
        }
        return response()->json($query->paginate(30));
    }

    public function approveGlobal(Request $request, string $id): JsonResponse
    {
        $review = CourseReview::with(['user', 'course'])->findOrFail($id);
        $review->update(["is_approved" => true]);
        $this->sendApprovalEmail($review);
        return response()->json($review);
    }

    public function unapproveGlobal(Request $request, string $id): JsonResponse
    {
        $review = CourseReview::with(['user', 'course'])->findOrFail($id);
        $review->update(["is_approved" => false]);
        $this->sendUnapprovalEmail($review);
        return response()->json($review);
    }

    public function reply(Request $request, string $id): JsonResponse
    {
        $data   = $request->validate(["admin_reply" => ["required", "string", "max:2000"]]);
        $review = CourseReview::with(['user', 'course'])->findOrFail($id);
        $review->update(["admin_reply" => $data["admin_reply"], "admin_reply_at" => now()]);

        if ($review->user && $review->course) {
            $courseUrl = config('app.frontend_url', config('app.url')) . '/courses/' . $review->course->slug;
            $stars     = str_repeat('★', (int) $review->rating) . str_repeat('☆', 5 - (int) $review->rating);
            EmailService::send($review->user->email, 'review_replied', [
                '{username}'      => $review->user->name,
                '{email}'         => $review->user->email,
                '{course_title}'  => $review->course->title,
                '{course_url}'    => $courseUrl,
                '{rating}'        => $review->rating . '/5 ' . $stars,
                '{admin_reply}'   => $data['admin_reply'],
                '{site_name}'     => AppModelsSetting::getValue('site_name', config('app.name')),
            ]);
        }

        return response()->json($review);
    }

    private function sendUnapprovalEmail(CourseReview $review): void
    {
        if (!$review->user || !$review->course) return;

        $courseUrl = config('app.frontend_url', config('app.url')) . '/courses/' . $review->course->slug;
        $stars     = str_repeat('★', (int) $review->rating) . str_repeat('☆', 5 - (int) $review->rating);

        EmailService::send($review->user->email, 'review_unapproved', [
            '{username}'     => $review->user->name,
            '{email}'        => $review->user->email,
            '{course_title}' => $review->course->title,
            '{course_url}'   => $courseUrl,
            '{rating}'       => $review->rating . '/5 ' . $stars,
            '{site_name}'    => AppModelsSetting::getValue('site_name', config('app.name')),
        ]);
    }

    private function sendDeletedEmail(CourseReview $review): void
    {
        if (!$review->user || !$review->course) return;

        $courseUrl = config('app.frontend_url', config('app.url')) . '/courses/' . $review->course->slug;

        EmailService::send($review->user->email, 'review_deleted', [
            '{username}'     => $review->user->name,
            '{email}'        => $review->user->email,
            '{course_title}' => $review->course->title,
            '{course_url}'   => $courseUrl,
            '{site_name}'    => AppModelsSetting::getValue('site_name', config('app.name')),
        ]);
    }

    private function sendApprovalEmail(CourseReview $review): void
    {
        if (!$review->user || !$review->course) return;

        $courseUrl = config('app.frontend_url', config('app.url')) . '/courses/' . $review->course->slug;
        $stars     = str_repeat('★', (int) $review->rating) . str_repeat('☆', 5 - (int) $review->rating);

        EmailService::send($review->user->email, 'review_approved', [
            '{username}'     => $review->user->name,
            '{email}'        => $review->user->email,
            '{course_title}' => $review->course->title,
            '{course_url}'   => $courseUrl,
            '{rating}'       => $review->rating . '/5 ' . $stars,
            '{site_name}'    => AppModelsSetting::getValue('site_name', config('app.name')),
        ]);
    }

    public function adminDestroyGlobal(Request $request, string $id): JsonResponse
    {
        $review = CourseReview::with(['user', 'course'])->findOrFail($id);
        $this->sendDeletedEmail($review);
        $review->delete();
        return response()->json(["message" => "Review deleted."]);
    }
}