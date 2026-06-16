<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlanItem;
use App\Models\Course;
use App\Models\AppointmentType;
use App\Models\Event;
use App\Models\Resource;
use App\Traits\HasTrash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class PlanController extends Controller
{
    use HasTrash;
    protected string $model = Plan::class;
    protected array $trashedWith = ['company:id,name'];

    public function index(): JsonResponse
    {
        $plans = Plan::with('items')->where("is_active", true)->orderBy("sort_order")->orderBy("id")->get();
        return response()->json(["data" => $plans->map(fn($p) => array_merge($p->toArray(), ['access' => $this->formatAccessPublic($p->items)]))]);
    }

    public function adminIndex(): JsonResponse
    {
        $plans = Plan::with(['company:id,name', 'items'])->orderBy('sort_order')->orderBy('id')->get();
        return response()->json(['data' => $plans->map(fn($p) => array_merge($p->toArray(), ['access' => $this->formatAccess($p->items)]))]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $items = $request->validate([
            'items'              => ['required', 'array'],
            'items.*.id'         => ['required', 'integer', 'exists:plans,id'],
            'items.*.sort_order' => ['required', 'integer'],
        ])['items'];
        foreach ($items as $item) {
            Plan::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }
        return response()->json(['message' => 'Order saved.']);
    }

    public function show(string $id): JsonResponse
    {
        $plan = Plan::with('items')->findOrFail($id);
        return response()->json(array_merge($plan->toArray(), ['access' => $this->formatAccessPublic($plan->items)]));
    }

    public function adminShow(string $id): JsonResponse
    {
        $plan = Plan::with(['company:id,name', 'items'])->findOrFail($id);
        return response()->json(array_merge($plan->toArray(), ['access' => $this->formatAccess($plan->items)]));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            "name"             => ["required", "string"],
            "banner"           => ["nullable", "string"],
            "thumbnail"        => ["nullable", "string"],
            "description"      => ["nullable", "string"],
            "price"            => ["required", "numeric", "min:0"],
            "interval"         => ["required", "in:month,year"],
            "interval_count"   => ["required", "integer", "min:1"],
            "stripe_price_id"  => ["nullable", "string"],
            "features"         => ["nullable"],
            "is_active"        => ["sometimes", "boolean"],
        ]);
        $plan = Plan::create($data);
        $this->applyAccess($plan, $request->input('access', []));
        return response()->json(array_merge($plan->toArray(), ['access' => $this->formatAccess($plan->items)]), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $plan = Plan::findOrFail($id);
        $data = $request->validate([
            "name"           => ["sometimes", "string"],
            "banner"         => ["nullable", "string"],
            "thumbnail"      => ["nullable", "string"],
            "description"    => ["nullable", "string"],
            "price"          => ["sometimes", "numeric", "min:0"],
            "interval"       => ["sometimes", "in:month,year"],
            "interval_count" => ["sometimes", "integer", "min:1"],
            "features"       => ["nullable"],
            "is_active"      => ["sometimes", "boolean"],
        ]);
        $plan->update($data);
        if ($request->has('access')) {
            $this->applyAccess($plan, $request->input('access', []));
        }
        $plan->load('items');
        return response()->json(array_merge($plan->toArray(), ['access' => $this->formatAccess($plan->items)]));
    }

    public function syncItems(Request $request, string $id): JsonResponse
    {
        $plan = Plan::findOrFail($id);
        $this->applyAccess($plan, $request->input('access', []));
        $plan->load('items');
        return response()->json(['message' => 'Access updated.', 'access' => $this->formatAccess($plan->items)]);
    }

    public function destroy(string $id): JsonResponse
    {
        Plan::findOrFail($id)->delete();
        return response()->json(["message" => "Plan deleted."]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function formatAccessPublic($items): array
    {
        $modelMap = [
            'course'           => [Course::class,          'title'],
            'appointment_type' => [AppointmentType::class, 'title'],
            'event'            => [Event::class,           'title'],
            'resource'         => [Resource::class,        'title'],
        ];

        $result = [];
        foreach ($modelMap as $type => [$model, $nameField]) {
            $typeItems  = $items->where('type', $type);
            $hasAll     = $typeItems->whereNull('item_id')->isNotEmpty();
            $isSentinel = !$hasAll && $typeItems->count() === 1 && $typeItems->first()?->item_id === 0;

            if ($hasAll || $typeItems->isEmpty()) {
                $result[$type . 's'] = ['mode' => 'all', 'items' => []];
            } elseif ($isSentinel) {
                // explicitly set to "no access" — hide on public page
                $result[$type . 's'] = ['mode' => 'specific', 'items' => []];
            } else {
                $ids = $typeItems->pluck('item_id')->filter()->values()->all();
                $named = $model::withoutGlobalScope('company')
                    ->whereIn('id', $ids)
                    ->get(['id', $nameField])
                    ->map(fn($r) => ['id' => $r->id, 'name' => $r->{$nameField}])
                    ->values()
                    ->all();
                $result[$type . 's'] = ['mode' => 'specific', 'items' => $named];
            }
        }
        return $result;
    }

    private function formatAccess($items): array
    {
        $types = ['course', 'appointment_type', 'event', 'resource'];
        $access = [];
        foreach ($types as $type) {
            $typeItems = $items->where('type', $type);
            $hasAll    = $typeItems->whereNull('item_id')->isNotEmpty();
            // item_id = 0 is a sentinel meaning "specific mode, nothing selected"
            $isSentinel = !$hasAll && $typeItems->count() === 1 && $typeItems->first()?->item_id === 0;

            if ($hasAll || $typeItems->isEmpty()) {
                $mode = 'all';
                $ids  = [];
            } elseif ($isSentinel) {
                $mode = 'specific';
                $ids  = [];
            } else {
                $mode = 'specific';
                $ids  = $typeItems->pluck('item_id')->filter()->values()->toArray();
            }

            $access[$type . 's'] = ['mode' => $mode, 'ids' => $ids];
        }
        return $access;
    }

    private function applyAccess(Plan $plan, array $access): void
    {
        $typeMap = [
            'courses'           => 'course',
            'appointment_types' => 'appointment_type',
            'events'            => 'event',
            'resources'         => 'resource',
        ];

        foreach ($typeMap as $key => $type) {
            if (!isset($access[$key])) continue;
            $plan->items()->where('type', $type)->delete();
            $cfg  = $access[$key];
            $mode = $cfg['mode'] ?? 'all';
            $ids  = $cfg['ids']  ?? [];

            if ($mode === 'all') {
                // no rows needed — absence means "all"
            } elseif (empty($ids)) {
                // specific mode, nothing selected — write sentinel so state persists
                $plan->items()->create(['type' => $type, 'item_id' => 0]);
            } else {
                foreach ($ids as $itemId) {
                    $plan->items()->create(['type' => $type, 'item_id' => $itemId]);
                }
            }
        }
    }
}
