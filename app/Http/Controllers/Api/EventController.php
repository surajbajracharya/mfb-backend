<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventTicket;
use App\Services\EmailService;
use App\Traits\HasTrash;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class EventController extends Controller
{
    use HasTrash;
    protected string $model = Event::class;
    protected array $trashedWith = ['company:id,name'];
    public function index(Request $request): JsonResponse
    {
        $query = Event::query();
        $query->where("status", $request->status ?? "published");
        return response()->json($query->orderBy("sort_order")->orderBy("starts_at")->paginate(20));
    }
    public function adminIndex(Request $request): JsonResponse
    {
        $query = Event::with(["company:id,name", "categories:id,name"]);
        if ($request->status) { $query->where("status", $request->status); }
        return response()->json(["data" => $query->orderBy("sort_order")->orderBy("id")->get()]);
    }
    public function show(string $slug): JsonResponse
    {
        $event = Event::where("slug", $slug)->firstOrFail();
        $event->available_tickets = $event->capacity - $event->tickets_sold;
        return response()->json($event);
    }
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            "title" => ["required", "string"],
            "slug" => ["required", "string", "unique:events,slug"],
            "description" => ["nullable", "string"],
            "is_online" => ["sometimes", "boolean"],
            "hero_image" => ["nullable", "string"],
            "organizer_name" => ["nullable", "string"],
            "organizer_email" => ["nullable", "email"],
            "organizer_phone" => ["nullable", "string"],
            "venue_name" => ["nullable", "string"],
            "venue_address" => ["nullable", "string"],
            "meeting_link" => ["nullable", "url"],
            "starts_at" => ["required", "string"],
            "ends_at" => ["required", "string"],
            "timezone" => ["required", "string"],
            "price" => ["required", "numeric", "min:0"],
            "capacity" => ["required", "integer", "min:1"],
            "status" => ["sometimes", "in:draft,published,cancelled,completed"],
            "meta_title" => ["nullable", "string", "max:120"],
            "meta_description" => ["nullable", "string", "max:320"],
            "og_image" => ["nullable", "string", "max:500"],
            "schema_markup" => ["nullable", "string"],
            "robots"        => ["nullable", "string"],
        ]);
        $data['starts_at'] = \Carbon\Carbon::createFromFormat('Y-m-d\TH:i', $data['starts_at'])->toDateTimeString();
        $data['ends_at']   = \Carbon\Carbon::createFromFormat('Y-m-d\TH:i', $data['ends_at'])->toDateTimeString();
        $categoryIds = $request->input('category_ids', []);
        Event::withoutGlobalScopes()->increment('sort_order');
        $data['sort_order'] = 0;
        $event = Event::create($data);
        if (!empty($categoryIds)) { $event->categories()->sync($categoryIds); }
        return response()->json($event->load('categories:id,name'), 201);
    }
    public function update(Request $request, string $id): JsonResponse
    {
        $event = Event::findOrFail($id);
        $data = $request->validate([
            "title" => ["sometimes", "string"],
            "slug" => ["sometimes", "string", "unique:events,slug," . $id],
            "description" => ["nullable", "string"],
            "is_online" => ["sometimes", "boolean"],
            "hero_image" => ["nullable", "string"],
            "starts_at" => ["sometimes", "string"],
            "ends_at" => ["sometimes", "string"],
            "timezone" => ["sometimes", "string"],
            "price" => ["sometimes", "numeric", "min:0"],
            "capacity" => ["sometimes", "integer", "min:1"],
            "status" => ["sometimes", "in:draft,published,cancelled,completed"],
            "venue_name" => ["nullable", "string"],
            "venue_address" => ["nullable", "string"],
            "organizer_name" => ["nullable", "string"],
            "organizer_email" => ["nullable", "email"],
            "organizer_phone" => ["nullable", "string"],
            "venue_phone" => ["nullable", "string"],
            "meta_title" => ["nullable", "string", "max:120"],
            "meta_description" => ["nullable", "string", "max:320"],
            "og_image" => ["nullable", "string", "max:500"],
            "schema_markup" => ["nullable", "string"],
            "robots"        => ["nullable", "string"],
        ]);
        if (!empty($data['starts_at'])) {
            $data['starts_at'] = \Carbon\Carbon::createFromFormat('Y-m-d\TH:i', $data['starts_at'])->toDateTimeString();
        }
        if (!empty($data['ends_at'])) {
            $data['ends_at'] = \Carbon\Carbon::createFromFormat('Y-m-d\TH:i', $data['ends_at'])->toDateTimeString();
        }
        if ($request->has('category_ids')) { $event->categories()->sync($request->input('category_ids', [])); }
        $prevStatus = $event->status;
        $event->update($data);

        // Notify all active ticket holders when event is cancelled
        if ($prevStatus !== 'cancelled' && $event->status === 'cancelled') {
            $tz       = $event->timezone ?? 'UTC';
            $tzShort  = last(explode('/', $tz));
            $eventDate = Carbon::parse($event->starts_at)->format('l, F j Y \a\t g:i A') . ' (' . $tzShort . ')';

            EventTicket::withoutCompanyScope()
                ->where('event_id', $event->id)
                ->whereNotIn('status', ['cancelled'])
                ->with('user')
                ->get()
                ->each(function (EventTicket $ticket) use ($event, $eventDate) {
                    if (!$ticket->user) return;
                    EmailService::send($ticket->user->email, 'event_cancelled', [
                        '{username}'       => $ticket->user->name,
                        '{email}'          => $ticket->user->email,
                        '{event_title}'    => $event->title,
                        '{event_date}'     => $eventDate,
                        '{ticket_number}'  => $ticket->ticket_code,
                        '{site_name}'      => AppModelsSetting::getValue('site_name', config('app.name')),
                    ]);
                });
        }

        return response()->json($event);
    }

    public function destroy(string $id): JsonResponse
    {
        Event::findOrFail($id)->delete();
        return response()->json(["message" => "Event deleted."]);
    }
    public function publish(string $id): JsonResponse
    {
        $event = Event::findOrFail($id);
        $event->update(["status" => "published"]);

        $tz          = $event->timezone ?? 'UTC';
        $tzShort     = last(explode('/', $tz));
        $frontendUrl = rtrim(config('app.frontend_url', url('/')), '/');
        $eventDate   = Carbon::parse($event->starts_at)->format('l, F j Y \a\t g:i A') . ' (' . $tzShort . ')';
        $eventUrl    = $frontendUrl . '/events/' . $event->slug;

        // Notify all active plan subscribers about the new event
        \App\Models\UserPlanSubscription::withoutCompanyScope()
            ->where('status', 'active')
            ->where('company_id', $event->company_id)
            ->with('user')
            ->get()
            ->each(function ($sub) use ($event, $eventDate, $eventUrl) {
                if (!$sub->user) return;
                EmailService::send($sub->user->email, 'event_published', [
                    '{username}'    => $sub->user->name,
                    '{email}'       => $sub->user->email,
                    '{event_title}' => $event->title,
                    '{event_date}'  => $eventDate,
                    '{event_url}'   => $eventUrl,
                    '{site_name}'   => AppModelsSetting::getValue('site_name', config('app.name')),
                ]);
            });

        return response()->json($event);
    }

    public function unpublish(string $id): JsonResponse
    {
        $event = Event::findOrFail($id);
        $event->update(["status" => "draft"]);
        return response()->json($event);
    }
}