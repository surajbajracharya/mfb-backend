@php
function certImgPath($val) {
    if (!$val) return null;
    if (str_starts_with($val, 'http')) {
        if (preg_match('#/storage/(.+)$#', $val, $m)) {
            return storage_path('app/public/' . $m[1]);
        }
        return null;
    }
    return storage_path('app/public/' . ltrim($val, '/'));
}
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
@page { margin: 0; size: A4 landscape; }
html, body { margin: 0; padding: 0; }

/* ── Outer page — single positioned ancestor for ALL absolute children ── */
.page {
    position: absolute;
    top: 2px; left: 2px; right: 2px; bottom: 2px;
    box-sizing: border-box;
    background: {{ $tpl->background_color }};
    border: {{ $tpl->border_width }}px solid {{ $tpl->border_color }};
    font-family: Georgia, 'Times New Roman', serif;
}

/* ── Inner decorative border — purely visual, no content ── */
.inner-border {
    position: absolute;
    top: 16px; left: 36px; right: 36px; bottom: 16px;
    border: 2px solid {{ $tpl->border_color }}33;
    box-sizing: border-box;
}

/* ── Top content: flows naturally from top ── */
/* padding matches: 36px (outer margin) + 22px (inner pad) = 58px left/right  */
/* top: 16px (outer margin) + 22px (inner pad) = 38px                          */
.content-wrap {
    padding: 70px 74px 0;
}

/* Logos */
.logos { overflow: hidden; margin-bottom: 14px; }
.logo-l { float: left; }
.logo-r { float: right; }
.logo-img { max-height: 56px; max-width: 160px; }
.cf:after { content: ''; display: table; clear: both; }

/* Title */
.cert-title {
    text-align: center;
    font-size: 34px;
    font-weight: bold;
    color: {{ $tpl->title_color }};
    letter-spacing: 4px;
    text-transform: uppercase;
    margin-top: 55px;
    margin-bottom: 12px;
}
.divider {
    width: 80px; height: 2px;
    background: {{ $tpl->border_color }};
    margin: 0 auto 14px;
}

/* Body */
.body { text-align: center; font-size: 16px; color: #374151; font-family: Georgia, 'Times New Roman', serif; }
.body p { margin-bottom: 6px; line-height: 1.7; }

/* ── Signature + meta — absolute at bottom, relative to .page ── */
/* bottom = 16px (outer margin) + 38px (inner pad) = 54px          */
/* left/right = 36px (outer margin) + 22px (inner pad) = 58px      */
.bottom {
    position: absolute;
    bottom: 54px;
    left: 58px;
    right: 58px;
    overflow: hidden;
    box-sizing: border-box;
}
.sig-block { float: left; text-align: center; }
.sig-img { max-height: 50px; max-width: 180px; display: block; margin: 0 auto 4px; }
.sig-line { border-top: 1.5px solid #555; width: 180px; margin: 4px auto 0; padding-top: 4px; }
.sig-label { font-size: 13px; color: #555; }
.cert-meta { float: right; text-align: right; font-size: 13px; color: #555; line-height: 1.9; }

/* ── Footer — absolute at very bottom ── */
/* bottom = 16px (outer margin) + 12px (inner pad) = 28px */
.footer {
    position: absolute;
    bottom: 28px;
    left: 36px; right: 36px;
    text-align: center;
    font-size: 10px;
    color: #aaa;
    letter-spacing: 1px;
}
</style>
</head>
<body>
<div class="page">

    {{-- Decorative inner border (visual only) --}}
    <div class="inner-border"></div>

    {{-- Top content: logos, title, body --}}
    <div class="content-wrap">
        <div class="logos cf">
            <div class="logo-l">
                @if(!empty($logoLeft) && certImgPath($logoLeft))
                    <img class="logo-img" src="{{ certImgPath($logoLeft) }}">
                @endif
            </div>
            <div class="logo-r">
                @if(!empty($logoRight) && certImgPath($logoRight))
                    <img class="logo-img" src="{{ certImgPath($logoRight) }}">
                @endif
            </div>
        </div>

        <div class="cert-title">{{ $tpl->title }}</div>
        <div class="divider"></div>
        <div class="body">{!! $body !!}</div>
    </div>

    {{-- Signature + meta: pinned to bottom of .page --}}
    <div class="bottom cf">
        <div class="sig-block">
            @if($tpl->signature_image && certImgPath($tpl->signature_image))
                <img class="sig-img" src="{{ certImgPath($tpl->signature_image) }}">
            @endif
            <div class="sig-line"></div>
            <div class="sig-label">{{ $tpl->signature_label }}</div>
        </div>
        <div class="cert-meta">
            @if($tpl->show_date)Date: <strong>{{ $issuedDate }}</strong><br>@endif
            @if($tpl->show_certificate_number)Certificate No: <strong>{{ $certificateNumber }}</strong>@endif
        </div>
    </div>

    {{-- Footer: very bottom --}}
    <div class="footer">{{ $tpl->footer_text ?: $companyName }}</div>

</div>
</body>
</html>
