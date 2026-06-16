<?php
namespace App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Services\EmailService;
use App\Traits\HasTrash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class AdminCourseController extends Controller
{
    use HasTrash;
    protected string $model = Course::class;
    protected array $trashedWith = ['category:id,name', 'company:id,name'];
    public function index(Request $request): JsonResponse
    {
        $courses = Course::withCount("enrollments")
            ->with(["category:id,name", "company:id,name"])
            ->orderBy("sort_order")
            ->orderBy("id")
            ->paginate(50);
        return response()->json($courses);
    }

    public function reorder(Request $request): JsonResponse
    {
        $items = $request->validate([
            'items'              => ['required', 'array'],
            'items.*.id'         => ['required', 'integer', 'exists:courses,id'],
            'items.*.sort_order' => ['required', 'integer'],
        ])['items'];

        foreach ($items as $item) {
            Course::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['message' => 'Order saved.']);
    }
    public function show(string $id): JsonResponse
    {
        return response()->json(Course::with(["sections.lectures", "category"])->findOrFail($id));
    }
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            "title" => ["required", "string"],
            "slug" => ["required", "string", "unique:courses,slug"],
            "short_description" => ["nullable", "string"],
            "description" => ["nullable", "string"],
            "price" => ["sometimes", "numeric", "min:0"],
            "compare_price" => ["nullable", "numeric", "min:0"],
            "type" => ["required", "in:free,premium"],
            "level" => ["nullable", "string"],
            "language" => ["required", "string"],
            "status" => ["sometimes", "in:draft,published,archived"],
            "category_id" => ["nullable", "exists:categories,id"],
            "has_certificate" => ["sometimes", "boolean"],
            "duration_hours" => ["sometimes", "numeric", "min:0"],
            "thumbnail" => ["nullable", "string", "max:500"],
            "intro_video" => ["nullable", "string", "max:500"],
            "what_you_learn" => ["nullable"],
            "requirements" => ["nullable"],
            "meta_title" => ["nullable", "string", "max:120"],
            "meta_description" => ["nullable", "string", "max:320"],
            "og_image" => ["nullable", "string", "max:500"],
            "schema_markup" => ["nullable", "string"],
            "robots"        => ["nullable", "string"],
        ]);
        $data["user_id"] = $request->user()->id;
        // Free courses always have zero price
        if (($data["type"] ?? null) === 'free') {
            $data["price"] = 0;
            $data["compare_price"] = null;
        }
        if (isset($data["what_you_learn"]) && is_string($data["what_you_learn"])) {
            $data["what_you_learn"] = array_filter(array_map("trim", explode("\n", $data["what_you_learn"])));
        }
        if (isset($data["requirements"]) && is_string($data["requirements"])) {
            $data["requirements"] = array_filter(array_map("trim", explode("\n", $data["requirements"])));
        }
        // Shift all existing courses down so the new one sits at position 0 (top of list).
        Course::withoutGlobalScopes()->increment('sort_order');
        $data['sort_order'] = 0;
        return response()->json(Course::create($data), 201);
    }
    public function update(Request $request, string $id): JsonResponse
    {
        $course = Course::findOrFail($id);
        $data = $request->validate([
            "title" => ["sometimes", "string"],
            "slug" => ["sometimes", "string", "unique:courses,slug," . $id],
            "short_description" => ["nullable", "string"],
            "description" => ["nullable", "string"],
            "price" => ["sometimes", "numeric", "min:0"],
            "compare_price" => ["nullable", "numeric", "min:0"],
            "type" => ["sometimes", "in:free,premium"],
            "level" => ["nullable", "string"],
            "language" => ["sometimes", "string"],
            "status" => ["sometimes", "in:draft,published,archived"],
            "category_id" => ["nullable", "exists:categories,id"],
            "has_certificate" => ["sometimes", "boolean"],
            "duration_hours" => ["sometimes", "numeric", "min:0"],
            "thumbnail" => ["nullable", "string", "max:500"],
            "intro_video" => ["nullable", "string", "max:500"],
            "what_you_learn" => ["nullable"],
            "requirements" => ["nullable"],
            "meta_title" => ["nullable", "string", "max:120"],
            "meta_description" => ["nullable", "string", "max:320"],
            "og_image" => ["nullable", "string", "max:500"],
            "schema_markup" => ["nullable", "string"],
            "robots"        => ["nullable", "string"],
        ]);
        // Free courses always have zero price
        if (($data["type"] ?? $course->type) === 'free') {
            $data["price"] = 0;
            $data["compare_price"] = null;
        }
        if (isset($data["what_you_learn"]) && is_string($data["what_you_learn"])) {
            $data["what_you_learn"] = array_filter(array_map("trim", explode("\n", $data["what_you_learn"])));
        }
        if (isset($data["requirements"]) && is_string($data["requirements"])) {
            $data["requirements"] = array_filter(array_map("trim", explode("\n", $data["requirements"])));
        }
        $course->update($data);
        return response()->json($course);
    }
    public function destroy(string $id): JsonResponse
    {
        Course::findOrFail($id)->delete();
        return response()->json(["message" => "Course deleted."]);
    }
    public function duplicate(string $id): JsonResponse
    {
        $original = Course::with('sections.lectures')->findOrFail($id);

        // Build a unique slug
        $baseSlug = $original->slug . '-copy';
        $slug = $baseSlug;
        $i = 2;
        while (Course::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $i++;
        }

        // Place the duplicate at the top of the list.
        Course::withoutGlobalScopes()->increment('sort_order');
        $newCourse = $original->replicate(['slug', 'status', 'enrollments_count']);
        $newCourse->title      = 'Copy of ' . $original->title;
        $newCourse->slug       = $slug;
        $newCourse->status     = 'draft';
        $newCourse->sort_order = 0;
        $newCourse->save();

        foreach ($original->sections as $section) {
            $newSection = $section->replicate(['course_id']);
            $newSection->course_id = $newCourse->id;
            $newSection->save();

            foreach ($section->lectures as $lecture) {
                $newLecture = $lecture->replicate(['section_id']);
                $newLecture->section_id = $newSection->id;
                $newLecture->save();
            }
        }

        return response()->json($newCourse, 201);
    }

    public function publish(string $id): JsonResponse
    {
        $course = Course::findOrFail($id);
        $wasArchived = $course->status === 'archived';
        $course->update(["status" => "published"]);

        // Only notify enrolled users when republishing an archived course
        if ($wasArchived) {
            $frontendUrl = rtrim(config('app.frontend_url', url('/')), '/');
            $courseUrl   = $frontendUrl . '/courses/' . $course->slug;

            Enrollment::where('course_id', $course->id)
                ->with('user')
                ->get()
                ->each(function (Enrollment $enrollment) use ($course, $courseUrl) {
                    if (!$enrollment->user) return;
                    EmailService::send($enrollment->user->email, 'course_published', [
                        '{username}'     => $enrollment->user->name,
                        '{email}'        => $enrollment->user->email,
                        '{course_title}' => $course->title,
                        '{course_url}'   => $courseUrl,
                        '{site_name}'    => AppModelsSetting::getValue('site_name', config('app.name')),
                    ]);
                });
        }

        return response()->json(["message" => "Course published."]);
    }

    public function archive(string $id): JsonResponse
    {
        $course = Course::findOrFail($id);
        $course->update(["status" => "archived"]);

        $frontendUrl = rtrim(config('app.frontend_url', url('/')), '/');

        Enrollment::where('course_id', $course->id)
            ->with('user')
            ->get()
            ->each(function (Enrollment $enrollment) use ($course, $frontendUrl) {
                if (!$enrollment->user) return;
                EmailService::send($enrollment->user->email, 'course_archived', [
                    '{username}'     => $enrollment->user->name,
                    '{email}'        => $enrollment->user->email,
                    '{course_title}' => $course->title,
                    '{dashboard_url}'=> $frontendUrl . '/dashboard',
                    '{site_name}'    => AppModelsSetting::getValue('site_name', config('app.name')),
                ]);
            });

        return response()->json(["message" => "Course archived."]);
    }
}