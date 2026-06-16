<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Course;
use App\Models\Event;
use App\Models\Resource;
use App\Models\AppointmentType;
use App\Models\Category;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminMenuController extends Controller
{
    // List all menus
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->header('X-Company-ID');
        $menus = Menu::where(function ($q) use ($companyId) {
                if ($companyId) $q->where('company_id', $companyId)->orWhereNull('company_id');
                else $q->whereNull('company_id');
            })
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'location']);

        return response()->json($menus);
    }

    // Get a single menu with its full item tree
    public function show(string $id): JsonResponse
    {
        $menu = Menu::findOrFail($id);
        $items = MenuItem::where('menu_id', $menu->id)
            ->orderBy('sort_order')
            ->get();

        $tree = $this->buildTree($items->toArray());

        return response()->json(['menu' => $menu, 'items' => $tree]);
    }

    // Create a new menu
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'location' => 'nullable|string|max:100']);
        $companyId = $request->header('X-Company-ID');

        $menu = Menu::create([
            'name'       => $data['name'],
            'slug'       => Str::slug($data['name']) . '-' . Str::random(4),
            'location'   => $data['location'] ?? null,
            'company_id' => $companyId ?: null,
        ]);

        return response()->json($menu, 201);
    }

    // Update menu name / location
    public function update(Request $request, string $id): JsonResponse
    {
        $menu = Menu::findOrFail($id);
        $data = $request->validate(['name' => 'sometimes|string|max:255', 'location' => 'nullable|string|max:100']);
        $menu->update($data);

        return response()->json($menu);
    }

    // Delete a menu (cascades to items)
    public function destroy(string $id): JsonResponse
    {
        Menu::findOrFail($id)->delete();
        return response()->json(['message' => 'Menu deleted.']);
    }

    // Save full item tree (called on "Save Menu")
    public function saveItems(Request $request, string $id): JsonResponse
    {
        $menu = Menu::findOrFail($id);
        $items = $request->validate(['items' => 'present|array'])['items'];
        $companyId = $request->header('X-Company-ID');

        // Delete all existing items and re-insert
        MenuItem::where('menu_id', $menu->id)->delete();

        $order = 0;
        $this->insertItems($items, $menu->id, null, $order, $companyId);

        $fresh = MenuItem::where('menu_id', $menu->id)->orderBy('sort_order')->get();
        return response()->json(['items' => $this->buildTree($fresh->toArray())]);
    }

    // Get available items to add (sources panel)
    public function available(Request $request): JsonResponse
    {
        $companyId = $request->header('X-Company-ID');

        $courses = Course::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->select('id', 'title as name', 'slug')->orderBy('title')->get()
            ->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug, 'type' => 'course', 'url' => '/courses/' . $c->slug]);

        $events = Event::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->select('id', 'title as name', 'slug')->orderBy('title')->get()
            ->map(fn($e) => ['id' => $e->id, 'name' => $e->name, 'slug' => $e->slug, 'type' => 'event', 'url' => '/events/' . $e->slug]);

        $resources = Resource::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->select('id', 'title as name', 'slug')->orderBy('title')->get()
            ->map(fn($r) => ['id' => $r->id, 'name' => $r->name, 'slug' => $r->slug, 'type' => 'resource', 'url' => '/resources/' . $r->slug]);

        $appointmentTypes = AppointmentType::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->select('id', 'title as name', 'slug')->orderBy('title')->get()
            ->map(fn($a) => ['id' => $a->id, 'name' => $a->name, 'slug' => $a->slug, 'type' => 'appointment_type', 'url' => '/appointments/' . $a->slug]);

        $categories = Category::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->select('id', 'name', 'slug')->orderBy('name')->get()
            ->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug, 'type' => 'category', 'url' => '/categories/' . $c->slug]);

        $archives = collect([
            ['id' => null, 'name' => 'Home',         'type' => 'archive', 'url' => '/'],
            ['id' => null, 'name' => 'Courses',       'type' => 'archive', 'url' => '/courses'],
            ['id' => null, 'name' => 'Events',        'type' => 'archive', 'url' => '/events'],
            ['id' => null, 'name' => 'Resources',     'type' => 'archive', 'url' => '/resources'],
            ['id' => null, 'name' => 'Appointments',  'type' => 'archive', 'url' => '/appointments'],
            ['id' => null, 'name' => 'Plans',         'type' => 'archive', 'url' => '/plans'],
        ]);

        $endpoints = collect([
            ['id' => null, 'name' => 'Login',         'type' => 'endpoint', 'url' => '/login'],
            ['id' => null, 'name' => 'Register',      'type' => 'endpoint', 'url' => '/register'],
            ['id' => null, 'name' => 'Dashboard',     'type' => 'endpoint', 'url' => '/dashboard'],
            ['id' => null, 'name' => 'Cart',          'type' => 'endpoint', 'url' => '/cart'],
            ['id' => null, 'name' => 'My Orders',     'type' => 'endpoint', 'url' => '/dashboard/orders'],
            ['id' => null, 'name' => 'My Courses',    'type' => 'endpoint', 'url' => '/dashboard/courses'],
            ['id' => null, 'name' => 'My Profile',    'type' => 'endpoint', 'url' => '/dashboard/profile'],
        ]);

        $pages = Page::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->where('status', 'published')
            ->select('id', 'title as name', 'slug')->orderBy('title')->get()
            ->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'type' => 'page', 'url' => '/pages/' . $p->slug]);

        return response()->json([
            'archives'         => $archives,
            'endpoints'        => $endpoints,
            'pages'            => $pages,
            'courses'          => $courses,
            'events'           => $events,
            'resources'        => $resources,
            'appointment_types'=> $appointmentTypes,
            'categories'       => $categories,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function buildTree(array $items, ?int $parentId = null): array
    {
        $tree = [];
        foreach ($items as $item) {
            if ((int)($item['parent_id'] ?? 0) === (int)($parentId ?? 0)) {
                $item['children'] = $this->buildTree($items, (int)$item['id']);
                $tree[] = $item;
            }
        }
        return $tree;
    }

    private function insertItems(array $items, int $menuId, ?int $parentId, int &$order, $companyId): void
    {
        foreach ($items as $item) {
            $created = MenuItem::create([
                'menu_id'    => $menuId,
                'parent_id'  => $parentId,
                'title'      => $item['title'],
                'type'       => $item['type'] ?? 'custom',
                'item_id'    => $item['item_id'] ?? null,
                'url'        => $item['url'] ?? null,
                'target'     => $item['target'] ?? '_self',
                'is_mega'    => !empty($item['is_mega']),
                'sort_order' => $order++,
                'company_id' => $companyId ?: null,
            ]);

            if (!empty($item['children'])) {
                $this->insertItems($item['children'], $menuId, $created->id, $order, $companyId);
            }
        }
    }
}
