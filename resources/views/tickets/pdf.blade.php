<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: DejaVu Sans, Arial, sans-serif;
    background: #eef0f6;
    padding: 24px;
}

.ticket {
    width: 100%;
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
}

/* ── TOP BAND ── */
.top-band {
    background: #312e81;
    padding: 28px 32px 22px;
    position: relative;
}
.top-band-accent {
    position: absolute;
    top: 0; right: 0;
    width: 200px; height: 100%;
    background: rgba(255,255,255,0.04);
    border-radius: 0 12px 0 120px;
}
.event-label {
    color: rgba(255,255,255,0.55);
    font-size: 9px;
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-bottom: 6px;
}
.event-title {
    color: #fff;
    font-size: 20px;
    font-weight: bold;
    line-height: 1.3;
    margin-bottom: 10px;
}
.status-pill {
    display: inline-block;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: bold;
    letter-spacing: 1px;
    text-transform: uppercase;
}
.pill-active    { background: #d1fae5; color: #065f46; }
.pill-used      { background: #e5e7eb; color: #6b7280; }
.pill-cancelled { background: #fee2e2; color: #991b1b; }

/* ── INFO SECTION ── */
.info-section {
    padding: 22px 32px;
    border-bottom: 1px solid #f3f4f6;
}
.info-table { width: 100%; border-collapse: collapse; }
.info-table td { padding: 6px 0; vertical-align: top; }
.info-table .lbl {
    color: #9ca3af;
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    width: 80px;
    padding-top: 7px;
}
.info-table .val {
    color: #1f2937;
    font-size: 12px;
    font-weight: bold;
    padding-left: 8px;
}

/* ── DIVIDER (perforated) ── */
.perforation {
    position: relative;
    height: 0;
    border-top: 2px dashed #d1d5db;
    margin: 0 32px;
}
.perf-circle-left, .perf-circle-right {
    position: absolute;
    top: -12px;
    width: 22px; height: 22px;
    border-radius: 50%;
    background: #eef0f6;
}
.perf-circle-left  { left:  -44px; }
.perf-circle-right { right: -44px; }

/* ── CODE SECTION ── */
.code-section {
    padding: 24px 32px 20px;
    text-align: center;
}
.code-label {
    color: #9ca3af;
    font-size: 9px;
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-bottom: 12px;
}
.code-box {
    display: inline-block;
    border: 2px dashed #a5b4fc;
    border-radius: 10px;
    padding: 14px 40px;
    background: #eef2ff;
}
.code-text {
    font-family: DejaVu Sans Mono, Courier New, monospace;
    font-size: 24px;
    font-weight: bold;
    color: #3730a3;
    letter-spacing: 6px;
}

/* ── FOOTER STRIP ── */
.footer-strip {
    background: #f9fafb;
    border-top: 1px solid #f3f4f6;
    padding: 16px 32px;
}
.footer-grid { width: 100%; border-collapse: collapse; }
.footer-grid td {
    width: 33.33%;
    text-align: center;
    padding: 0 8px;
    vertical-align: middle;
}
.footer-grid td + td { border-left: 1px solid #e5e7eb; }
.f-label {
    color: #9ca3af;
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    margin-bottom: 4px;
}
.f-val {
    color: #111827;
    font-size: 14px;
    font-weight: bold;
}
.f-sub {
    color: #6b7280;
    font-size: 10px;
    margin-top: 2px;
}

/* ── ATTENDEE ROW ── */
.attendee-row {
    background: #312e81;
    padding: 10px 32px;
    display: table;
    width: 100%;
}
.att-label {
    display: table-cell;
    color: rgba(255,255,255,0.55);
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    vertical-align: middle;
    width: 80px;
}
.att-name {
    display: table-cell;
    color: #fff;
    font-size: 13px;
    font-weight: bold;
    vertical-align: middle;
}
.att-qty {
    display: table-cell;
    color: rgba(255,255,255,0.7);
    font-size: 11px;
    text-align: right;
    vertical-align: middle;
}
</style>
</head>
<body>
@php
function ticketImgPath($val) {
    if (!$val) return null;
    if (str_starts_with($val, 'http')) {
        if (preg_match('#/storage/(.+)$#', $val, $m))
            return storage_path('app/public/' . $m[1]);
        return null;
    }
    return storage_path('app/public/' . $val);
}
    $imgPath   = ticketImgPath($ticket->event->hero_image ?? null);
    $hasImage  = $imgPath && file_exists($imgPath);
    $statusKey = $ticket->status;
    $pillClass = 'pill-' . $statusKey;
    $starts    = \Carbon\Carbon::parse($ticket->event->starts_at);
    $ends      = \Carbon\Carbon::parse($ticket->event->ends_at);
@endphp

<div class="ticket">

    {{-- TOP BAND --}}
    <div class="top-band">
        <div class="top-band-accent"></div>
        <div class="event-label">Event Ticket</div>
        <div class="event-title">{{ $ticket->event->title }}</div>
        <span class="status-pill {{ $pillClass }}">{{ ucfirst($ticket->status) }}</span>
    </div>

    {{-- INFO --}}
    <div class="info-section">
        <table class="info-table">
            <tr>
                <td class="lbl">Date</td>
                <td class="val">{{ $starts->format('l, F j, Y') }}</td>
            </tr>
            <tr>
                <td class="lbl">Time</td>
                <td class="val">{{ $starts->format('g:i A') }} – {{ $ends->format('g:i A') }}
                    <span style="color:#6b7280; font-size:10px; font-weight:normal;">({{ $ticket->event->timezone }})</span>
                </td>
            </tr>
            @if($ticket->event->venue_name)
            <tr>
                <td class="lbl">Venue</td>
                <td class="val">{{ $ticket->event->venue_name }}
                    @if($ticket->event->venue_address)
                        <br><span style="color:#6b7280; font-size:10px; font-weight:normal;">{{ $ticket->event->venue_address }}</span>
                    @endif
                </td>
            </tr>
            @endif
            @if($ticket->event->is_online && $ticket->event->meeting_link)
            <tr>
                <td class="lbl">Link</td>
                <td class="val" style="font-size:10px;">{{ $ticket->event->meeting_link }}</td>
            </tr>
            @endif
        </table>
    </div>

    {{-- PERFORATION --}}
    <div style="position:relative; padding: 0 10px; margin: 0;">
        <div class="perforation">
            <div class="perf-circle-left"></div>
            <div class="perf-circle-right"></div>
        </div>
    </div>

    {{-- TICKET CODE --}}
    <div class="code-section">
        <div class="code-label">Ticket Code</div>
        <div class="code-box">
            <div class="code-text">{{ $ticket->ticket_code }}</div>
        </div>
    </div>

    {{-- FOOTER STRIP --}}
    <div class="footer-strip">
        <table class="footer-grid">
            <tr>
                <td>
                    <div class="f-label">Quantity</div>
                    <div class="f-val">{{ $ticket->quantity }}</div>
                    <div class="f-sub">{{ $ticket->quantity == 1 ? 'ticket' : 'tickets' }}</div>
                </td>
                <td>
                    <div class="f-label">Issued On</div>
                    <div class="f-val">{{ $ticket->created_at->format('M j, Y') }}</div>
                </td>
                <td>
                    <div class="f-label">Order Ref</div>
                    <div class="f-val">#{{ str_pad($ticket->id, 5, '0', STR_PAD_LEFT) }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ATTENDEE ROW --}}
    <div class="attendee-row">
        <div class="att-label">Attendee</div>
        <div class="att-name">{{ $ticket->user->name }}</div>
        <div class="att-qty">{{ $ticket->user->email }}</div>
    </div>

</div>
</body>
</html>
