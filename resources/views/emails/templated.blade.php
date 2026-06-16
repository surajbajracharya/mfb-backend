<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f3f4f6; color: #111827; }
        .wrapper { max-width: 600px; margin: 0 auto; }
        .header {
            background: {{ $settings['header_bg_color'] ?? '#6366f1' }};
            padding: 24px 32px;
            text-align: center;
        }
        .header img { max-height: 48px; max-width: 200px; }
        .header-tagline {
            color: {{ $settings['header_text_color'] ?? '#ffffff' }};
            font-size: 13px;
            margin-top: 6px;
            opacity: 0.85;
        }
        .body {
            background: #f9fafb;
            padding: 40px 32px;
            font-size: 15px;
            line-height: 1.65;
            color: #374151;
        }
        .body h2 { color: #111827; margin-bottom: 12px; }
        .body p { margin-bottom: 12px; }
        .body a { color: {{ $settings['header_bg_color'] ?? '#6366f1' }}; }
        .body table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        .body td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; font-size: 14px; }
        .footer {
            background: {{ $settings['footer_bg_color'] ?? '#f9fafb' }};
            padding: 24px 32px;
            text-align: center;
            font-size: 12px;
            color: {{ $settings['footer_text_color'] ?? '#6b7280' }};
            border-top: 1px solid #e5e7eb;
        }
        .footer a { color: {{ $settings['footer_text_color'] ?? '#6b7280' }}; text-decoration: underline; }
        .social-links { margin-top: 12px; }
        .social-links a { margin: 0 6px; color: {{ $settings['footer_text_color'] ?? '#6b7280' }}; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
<div class="wrapper">

    {{-- Header --}}
    <div class="header">
        @if(!empty($settings['header_logo']))
            <img src="{{ $settings['header_logo'] }}" alt="{{ $settings['site_name'] ?? config('app.name') }}">
        @else
            <div style="color:{{ $settings['header_text_color'] ?? '#ffffff' }};font-size:22px;font-weight:700;letter-spacing:1px;">
                {{ $settings['site_name'] ?? config('app.name') }}
            </div>
        @endif
        @if(!empty($settings['header_tagline']))
            <div class="header-tagline">{{ $settings['header_tagline'] }}</div>
        @endif
    </div>

    {{-- Body --}}
    <div class="body">
        {!! $bodyHtml !!}
    </div>

    {{-- Footer --}}
    <div class="footer">
        @if(!empty($settings['footer_html']))
            {!! $settings['footer_html'] !!}
        @else
            <p>© {{ date('Y') }} {{ $settings['site_name'] ?? config('app.name') }}. All rights reserved.</p>
        @endif

        @php $social = $settings['social_links'] ?? []; @endphp
        @if(!empty(array_filter($social)))
            <div class="social-links">
                @if(!empty($social['facebook']))<a href="{{ $social['facebook'] }}">Facebook</a>@endif
                @if(!empty($social['twitter']))<a href="{{ $social['twitter'] }}">Twitter</a>@endif
                @if(!empty($social['instagram']))<a href="{{ $social['instagram'] }}">Instagram</a>@endif
                @if(!empty($social['linkedin']))<a href="{{ $social['linkedin'] }}">LinkedIn</a>@endif
            </div>
        @endif
    </div>

</div>
</body>
</html>
