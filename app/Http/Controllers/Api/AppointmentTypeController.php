<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\AppointmentType;
use App\Traits\HasTrash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class AppointmentTypeController extends Controller
{
    use HasTrash;
    protected string $model = AppointmentType::class;
    protected array $trashedWith = ['company:id,name'];
    public function index(): JsonResponse
    {
        return response()->json(["data" => AppointmentType::with(['consentTemplate:id,name,disclaimer_html,fields', 'categories:id,name'])->where("is_active", true)->orderBy("sort_order")->orderBy("id")->get()]);
    }
    public function adminIndex(): JsonResponse
    {
        return response()->json(["data" => AppointmentType::with(['consentTemplate:id,name', 'company:id,name', 'categories:id,name'])->orderBy("sort_order")->orderBy("id")->get()]);
    }
    public function reorder(Request $request): JsonResponse
    {
        $items = $request->validate([
            'items'              => ['required', 'array'],
            'items.*.id'         => ['required', 'integer', 'exists:appointment_types,id'],
            'items.*.sort_order' => ['required', 'integer'],
        ])['items'];
        foreach ($items as $item) {
            AppointmentType::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }
        return response()->json(['message' => 'Order saved.']);
    }
    public function show(string $slug): JsonResponse
    {
        $type = AppointmentType::with(['consentTemplate:id,name,disclaimer_html,fields', 'categories:id,name'])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();
        return response()->json($type);
    }
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            "title"               => ["required", "string"],
            "slug"                => ["nullable", "string", "unique:appointment_types,slug"],
            "meta_title"          => ["nullable", "string", "max:120"],
            "meta_description"    => ["nullable", "string", "max:320"],
            "og_image"            => ["nullable", "string"],
            "schema_markup"       => ["nullable", "string"],
            "robots"              => ["nullable", "string"],
            "short_description"   => ["nullable", "string", "max:255"],
            "description"         => ["nullable", "string"],
            "price"               => ["required", "numeric", "min:0"],
            "duration_minutes"    => ["required", "integer", "min:5"],
            "break_minutes"       => ["sometimes", "integer", "min:0"],
            "images"              => ["sometimes", "nullable", "array"],
            "images.*"            => ["string"],
            "is_active"           => ["sometimes", "boolean"],
            "consent_template_id" => ["nullable", "exists:consent_templates,id"],
            "mandatory_fields"              => ["sometimes", "nullable", "array"],
            "mandatory_fields.*.show"       => ["sometimes", "boolean"],
            "mandatory_fields.*.required"   => ["sometimes", "boolean"],
        ]);
        if (empty($data['slug'])) {
            $base = Str::slug($data['title']);
            $slug = $base;
            $i = 1;
            while (AppointmentType::where('slug', $slug)->exists()) {
                $slug = $base . '-' . $i++;
            }
            $data['slug'] = $slug;
        }
        $categoryIds = $request->input('category_ids', []);
        AppointmentType::withoutGlobalScopes()->increment('sort_order');
        $data['sort_order'] = 0;
        $type = AppointmentType::create($data);
        if (!empty($categoryIds)) {
            $type->categories()->sync($categoryIds);
        }
        return response()->json($type->load('categories:id,name'), 201);
    }
    public function update(Request $request, string $id): JsonResponse
    {
        $type = AppointmentType::findOrFail($id);
        $validated = $request->validate([
            "title"               => ["sometimes", "string"],
            "slug"                => ["sometimes", "string", "unique:appointment_types,slug," . $id],
            "meta_title"          => ["nullable", "string", "max:120"],
            "meta_description"    => ["nullable", "string", "max:320"],
            "og_image"            => ["nullable", "string"],
            "schema_markup"       => ["nullable", "string"],
            "robots"              => ["nullable", "string"],
            "short_description"   => ["nullable", "string", "max:255"],
            "description"         => ["nullable", "string"],
            "price"               => ["sometimes", "numeric", "min:0"],
            "duration_minutes"    => ["sometimes", "integer", "min:5"],
            "break_minutes"       => ["sometimes", "integer", "min:0"],
            "images"              => ["sometimes", "nullable", "array"],
            "images.*"            => ["string"],
            "is_active"           => ["sometimes", "boolean"],
            "consent_template_id" => ["nullable", "exists:consent_templates,id"],
            "mandatory_fields"              => ["sometimes", "nullable", "array"],
            "mandatory_fields.*.show"       => ["sometimes", "boolean"],
            "mandatory_fields.*.required"   => ["sometimes", "boolean"],
        ]);
        // Auto-generate slug from new title if title changed and no slug provided
        if (isset($validated['title']) && !isset($validated['slug'])) {
            $base = Str::slug($validated['title']);
            $slug = $base;
            $i = 1;
            while (AppointmentType::where('slug', $slug)->where('id', '!=', $id)->exists()) {
                $slug = $base . '-' . $i++;
            }
            $validated['slug'] = $slug;
        }
        $type->update($validated);
        if ($request->has('category_ids')) {
            $type->categories()->sync($request->input('category_ids', []));
        }
        return response()->json($type->load('categories:id,name'));
    }
    public function destroy(string $id): JsonResponse
    {
        AppointmentType::findOrFail($id)->delete();
        return response()->json(["message" => "Appointment type deleted."]);
    }
}
