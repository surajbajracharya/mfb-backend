<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Resource;
use App\Services\EmailService;
use App\Services\PlanAccessService;
use App\Traits\HasTrash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class ResourceController extends Controller
{
    use HasTrash;
    protected string $model = Resource::class;
    protected array $trashedWith = ['company:id,name'];
    public function index(Request $request): JsonResponse
    {
        $query = Resource::where("status", $request->status ?? "published");
        if ($request->type) { $query->where("type", $request->type); }
        if ($request->sort === 'latest') {
            $query->orderByDesc('id');
        } else {
            $query->orderBy("sort_order")->orderBy("id");
        }
        return response()->json($query->paginate($request->per_page ?? 20));
    }
    public function adminIndex(Request $request): JsonResponse
    {
        $ctx   = app(\App\Services\TenantContext::class);
        $query = Resource::withoutCompanyScope()->with("company:id,name");
        if (!$ctx->isGlobal() && $ctx->companyId() !== null) {
            $cid = $ctx->companyId();
            $query->where(fn ($q) => $q->where('company_id', $cid)->orWhereNull('company_id'));
        }
        if ($request->type) { $query->where("type", $request->type); }
        return response()->json(["data" => $query->orderBy("sort_order")->orderBy("id")->get()]);
    }
    public function reorder(Request $request): JsonResponse
    {
        $items = $request->validate([
            'items'              => ['required', 'array'],
            'items.*.id'         => ['required', 'integer', 'exists:resources,id'],
            'items.*.sort_order' => ['required', 'integer'],
        ])['items'];
        foreach ($items as $item) {
            Resource::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }
        return response()->json(['message' => 'Order saved.']);
    }
    public function show(string $slug): JsonResponse
    {
        $resource = Resource::where("slug", $slug)->where("status", "published")->firstOrFail();

        $user      = auth('sanctum')->user();
        $hasAccess = (bool) $resource->is_free;

        if (!$hasAccess && $user) {
            $hasAccess = \App\Models\OrderItem::whereHas('order', function ($q) use ($user) {
                    $q->where('user_id', $user->id)->where('status', 'paid');
                })
                ->where('purchasable_type', Resource::class)
                ->where('purchasable_id', $resource->id)
                ->exists();

            if (!$hasAccess) {
                $hasAccess = PlanAccessService::userHasAccess($user->id, 'resource', $resource->id);
            }
        }

        $data               = $resource->toArray();
        $data['has_access'] = $hasAccess;

        // Strip gated content from response if user has no access
        if (!$hasAccess) {
            unset($data['content'], $data['file_url'], $data['external_url']);
        }

        // Count as viewed only when user actually accesses the content
        if ($user && $hasAccess) {
            \App\Models\ResourceView::updateOrCreate(
                ['user_id' => $user->id, 'resource_id' => $resource->id],
                ['viewed_at' => now()]
            );
        }

        return response()->json($data);
    }

    public function download(string $slug): JsonResponse
    {
        $resource = Resource::where("slug", $slug)->where("status", "published")->firstOrFail();

        if (!$resource->is_free) {
            $user = auth('sanctum')->user();
            if (!$user) {
                return response()->json(['message' => 'Authentication required.'], 401);
            }
            $hasPurchased = \App\Models\OrderItem::whereHas('order', function ($q) use ($user) {
                    $q->where('user_id', $user->id)->where('status', 'paid');
                })
                ->where('purchasable_type', Resource::class)
                ->where('purchasable_id', $resource->id)
                ->exists();
            if (!$hasPurchased) {
                $hasPurchased = PlanAccessService::userHasAccess($user->id, 'resource', $resource->id);
            }

            if (!$hasPurchased) {
                return response()->json(['message' => 'Purchase required.'], 403);
            }
        }

        $resource->increment("download_count");

        // Track per-user view
        $user = $user ?? auth('sanctum')->user();
        if ($user) {
            \App\Models\ResourceView::updateOrCreate(
                ['user_id' => $user->id, 'resource_id' => $resource->id],
                ['viewed_at' => now()]
            );
        }

        return response()->json([
            'url'            => $resource->file_url ?? $resource->external_url,
            'download_count' => $resource->download_count,
        ]);
    }
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            "title" => ["required", "string"],
            "slug" => ["required", "string", "unique:resources,slug"],
            "excerpt" => ["nullable", "string"],
            "content" => ["nullable", "string"],
            "meta_title" => ["nullable", "string", "max:120"],
            "meta_description" => ["nullable", "string", "max:320"],
            "og_image" => ["nullable", "string", "max:500"],
            "schema_markup" => ["nullable", "string"],
            "robots"        => ["nullable", "string"],
            "thumbnail" => ["nullable", "string"],
            "file_url" => ["nullable", "string"],
            "external_url" => ["nullable", "url"],
            "type" => ["required", "in:blog,video,audio,pdf"],
            "is_free" => ["sometimes", "boolean"],
            "price"   => ["sometimes", "numeric", "min:0"],
            "status" => ["sometimes", "in:draft,published,archived"],
        ]);
        if (isset($data['is_free']) && $data['is_free']) { $data['price'] = 0; }
        $data["user_id"] = $request->user()->id;
        Resource::withoutGlobalScopes()->increment('sort_order');
        $data['sort_order'] = 0;
        return response()->json(Resource::create($data), 201);
    }
    public function update(Request $request, string $id): JsonResponse
    {
        $resource  = Resource::findOrFail($id);
        $validated = $request->validate([
            "title"            => ["sometimes", "string"],
            "slug"             => ["sometimes", "string", "unique:resources,slug," . $id],
            "excerpt"          => ["nullable", "string"],
            "content"          => ["nullable", "string"],
            "meta_title"       => ["nullable", "string", "max:120"],
            "meta_description" => ["nullable", "string", "max:320"],
            "og_image"         => ["nullable", "string", "max:500"],
            "schema_markup"    => ["nullable", "string"],
            "robots"           => ["nullable", "string"],
            "thumbnail"        => ["nullable", "string"],
            "file_url"         => ["nullable", "string"],
            "external_url"     => ["nullable", "url"],
            "type"             => ["sometimes", "in:blog,video,audio,pdf"],
            "is_free"          => ["sometimes", "boolean"],
            "price"            => ["sometimes", "numeric", "min:0"],
            "status"           => ["sometimes", "in:draft,published,archived"],
        ]);
        if (isset($validated['is_free']) && $validated['is_free']) { $validated['price'] = 0; }
        $resource->update($validated);
        return response()->json($resource);
    }
    public function destroy(string $id): JsonResponse
    {
        Resource::findOrFail($id)->delete();
        return response()->json(["message" => "Resource deleted."]);
    }
    public function publish(string $id): JsonResponse
    {
        $resource = Resource::findOrFail($id);
        $resource->update(["status" => "published", "published_at" => now()]);

        $frontendUrl  = rtrim(config('app.frontend_url', url('/')), '/');
        $resourceUrl  = $frontendUrl . '/resources/' . $resource->slug;

        // Notify active plan subscribers about the new resource
        \App\Models\UserPlanSubscription::withoutCompanyScope()
            ->where('status', 'active')
            ->where('company_id', $resource->company_id)
            ->with('user')
            ->get()
            ->each(function ($sub) use ($resource, $resourceUrl) {
                if (!$sub->user) return;
                EmailService::send($sub->user->email, 'resource_published', [
                    '{username}'       => $sub->user->name,
                    '{email}'          => $sub->user->email,
                    '{resource_title}' => $resource->title,
                    '{resource_url}'   => $resourceUrl,
                    '{site_name}'      => AppModelsSetting::getValue('site_name', config('app.name')),
                ]);
            });

        return response()->json($resource);
    }

    public function archive(string $id): JsonResponse
    {
        $resource = Resource::findOrFail($id);
        $resource->update(["status" => "archived"]);
        return response()->json($resource);
    }
}
