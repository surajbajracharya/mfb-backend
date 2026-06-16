<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Traits\HasTrash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class CategoryController extends Controller
{
    use HasTrash;
    protected string $model = Category::class;
    protected array $trashedWith = ['company:id,name'];
    public function index(): JsonResponse
    {
        $categories = Category::with("children")->orderBy("sort_order")->orderBy("id")->get();
        return response()->json(["data" => $categories]);
    }
    public function adminIndex(): JsonResponse
    {
        $categories = Category::with(["parent", "company:id,name"])->orderBy("sort_order")->orderBy("id")->get();
        return response()->json(["data" => $categories]);
    }
    public function reorder(Request $request): JsonResponse
    {
        $items = $request->validate([
            'items'              => ['required', 'array'],
            'items.*.id'         => ['required', 'integer', 'exists:categories,id'],
            'items.*.sort_order' => ['required', 'integer'],
        ])['items'];
        foreach ($items as $item) {
            Category::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }
        return response()->json(['message' => 'Order saved.']);
    }
    public function show(string $slug): JsonResponse
    {
        $category = Category::where("slug", $slug)->with(["children", "parent"])->firstOrFail();
        return response()->json($category);
    }
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            "name" => ["required", "string", "max:255"],
            "slug" => ["required", "string", "unique:categories,slug"],
            "description" => ["nullable", "string"],
            "image" => ["nullable", "string"],
            "parent_id" => ["nullable", "exists:categories,id"],
        ]);
        return response()->json(Category::create($data), 201);
    }
    public function update(Request $request, string $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $data = $request->validate([
            "name" => ["sometimes", "string", "max:255"],
            "slug" => ["sometimes", "string", "unique:categories,slug," . $id],
            "description" => ["nullable", "string"],
            "image" => ["nullable", "string"],
            "parent_id" => ["nullable", "exists:categories,id"],
        ]);
        $category->update($data);
        return response()->json($category);
    }
    public function destroy(string $id): JsonResponse
    {
        Category::findOrFail($id)->delete();
        return response()->json(["message" => "Category deleted."]);
    }
}
