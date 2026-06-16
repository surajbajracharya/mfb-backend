<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Services\EmailService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
class EventTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tickets = $request->user()->eventTickets()
            ->with(["event:id,title,slug,starts_at,ends_at,venue_name,venue_address,hero_image,is_online,meeting_link,timezone"])
            ->latest()->get();
        return response()->json(["data" => $tickets]);
    }
    public function show(Request $request, string $id): JsonResponse
    {
        return response()->json($request->user()->eventTickets()->with("event")->findOrFail($id));
    }
    public function adminCancel(string $id): JsonResponse
    {
        $ticket = \App\Models\EventTicket::with(['user', 'event'])->findOrFail($id);
        if ($ticket->status === 'cancelled') {
            return response()->json(['message' => 'Ticket is already cancelled.'], 422);
        }
        $ticket->update(['status' => 'cancelled']);
        // Restore capacity on the event
        if ($ticket->event_id) {
            \App\Models\Event::where('id', $ticket->event_id)
                ->decrement('tickets_sold', $ticket->quantity);
        }

        if ($ticket->user && $ticket->event) {
            EmailService::send($ticket->user->email, 'event_ticket_cancelled', [
                '{username}'      => $ticket->user->name,
                '{email}'         => $ticket->user->email,
                '{event_title}'   => $ticket->event->title,
                '{ticket_number}' => $ticket->ticket_code,
                '{site_name}'     => AppModelsSetting::getValue('site_name', config('app.name')),
            ]);
        }

        return response()->json(['message' => 'Ticket cancelled.', 'ticket' => $ticket]);
    }

    public function download(Request $request, string $id): Response
    {
        $ticket = $request->user()->eventTickets()
            ->with(['event', 'user'])
            ->findOrFail($id);

        $pdf = Pdf::loadView('tickets.pdf', compact('ticket'))
            ->setPaper('a4', 'portrait');

        return $pdf->download('ticket-' . $ticket->ticket_code . '.pdf');
    }
}