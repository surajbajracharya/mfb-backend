<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body {
    font-family: 'DejaVu Sans', Arial, sans-serif;
    font-size: 11px;
    color: #1a1a1a;
    margin: 0;
    padding: 0;
    line-height: 1.5;
  }
  .page {
    padding: 40px 48px;
    max-width: 760px;
    margin: 0 auto;
  }
  .header {
    border-bottom: 2px solid #4f46e5;
    padding-bottom: 16px;
    margin-bottom: 24px;
  }
  .header h1 {
    font-size: 20px;
    font-weight: bold;
    color: #1e1b4b;
    margin: 0 0 4px 0;
  }
  .header p {
    font-size: 11px;
    color: #6b7280;
    margin: 0;
  }
  .section {
    margin-bottom: 24px;
  }
  .section-title {
    font-size: 10px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #6b7280;
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 6px;
    margin-bottom: 12px;
  }
  .info-row {
    display: flex;
    gap: 8px;
    margin-bottom: 6px;
  }
  .info-label {
    font-weight: bold;
    color: #374151;
    min-width: 130px;
    flex-shrink: 0;
  }
  .info-value {
    color: #111827;
  }
  .disclaimer-text {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    padding: 12px 14px;
    font-size: 10.5px;
    color: #374151;
    line-height: 1.6;
    white-space: pre-wrap;
  }
  .field-block {
    margin-bottom: 12px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f3f4f6;
  }
  .field-block:last-child {
    border-bottom: none;
  }
  .field-label {
    font-weight: bold;
    color: #374151;
    margin-bottom: 3px;
  }
  .field-required {
    color: #ef4444;
    margin-left: 2px;
  }
  .field-answer {
    color: #111827;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 3px;
    padding: 5px 8px;
    min-height: 20px;
  }
  .field-answer.empty {
    color: #9ca3af;
    font-style: italic;
  }
  .agreement-box {
    border: 2px solid #16a34a;
    border-radius: 6px;
    padding: 14px 16px;
    background: #f0fdf4;
  }
  .agreement-check {
    font-size: 16px;
    color: #16a34a;
    margin-right: 8px;
    font-weight: bold;
  }
  .agreement-text {
    font-size: 11px;
    font-weight: bold;
    color: #14532d;
    display: inline;
  }
  .agreement-meta {
    font-size: 10px;
    color: #166534;
    margin-top: 6px;
  }
  .footer {
    margin-top: 32px;
    padding-top: 12px;
    border-top: 1px solid #e5e7eb;
    font-size: 9.5px;
    color: #9ca3af;
    text-align: center;
  }
  .heading-field {
    font-size: 13px;
    font-weight: bold;
    color: #1e1b4b;
    padding-top: 8px;
    margin-bottom: 4px;
    border-bottom: 1px solid #e0e7ff;
  }
  .paragraph-field {
    color: #6b7280;
    font-style: italic;
  }
</style>
</head>
<body>
<div class="page">

  <!-- Header -->
  <div class="header">
    @php
      $logoLocalPath = null;
      if (!empty($companyLogo)) {
        if (preg_match('/storage\/(uploads\/.+)/', $companyLogo, $m)) {
          $logoLocalPath = storage_path('app/public/' . $m[1]);
        } else {
          $logoLocalPath = storage_path('app/public/' . ltrim(parse_url($companyLogo, PHP_URL_PATH), '/'));
        }
        if (!file_exists($logoLocalPath)) $logoLocalPath = null;
      }
    @endphp
    <table style="width:100%; border-collapse:collapse; margin-bottom:8px;">
      <tr>
        @if($logoLocalPath)
        <td style="width:56px; vertical-align:middle; padding-right:12px;">
          <img src="{{ $logoLocalPath }}" style="height:48px; width:auto; display:block;">
        </td>
        @endif
        <td style="vertical-align:middle;">
          <div style="font-size:20px; font-weight:bold; color:#1e1b4b;">Consent Form Record</div>
          <div style="font-size:11px; color:#4f46e5; font-weight:600; margin-top:2px;">{{ $companyName }}</div>
        </td>
      </tr>
    </table>
    <p>{{ $templateName }} &nbsp;&bull;&nbsp; Generated: {{ $generatedAt }}</p>
  </div>

  <!-- Appointment Details -->
  <div class="section">
    <div class="section-title">Appointment Details</div>
    <div class="info-row">
      <span class="info-label">Client Name:</span>
      <span class="info-value">{{ $userName }}</span>
    </div>
    <div class="info-row">
      <span class="info-label">Email:</span>
      <span class="info-value">{{ $userEmail }}</span>
    </div>
    <div class="info-row">
      <span class="info-label">Session Type:</span>
      <span class="info-value">{{ $appointmentType }}</span>
    </div>
    <div class="info-row">
      <span class="info-label">Scheduled Date/Time:</span>
      <span class="info-value">{{ $scheduledAt }}</span>
    </div>
    <div class="info-row">
      <span class="info-label">Appointment ID:</span>
      <span class="info-value">#{{ $appointmentId }}</span>
    </div>
  </div>

  @if($disclaimer)
  <!-- Disclaimer -->
  <div class="section">
    <div class="section-title">Terms &amp; Disclaimer</div>
    <div class="disclaimer-text">{{ $disclaimer }}</div>
  </div>
  @endif

  @if(count($fields) > 0)
  <!-- Form Responses -->
  <div class="section">
    <div class="section-title">Client Responses</div>
    @foreach($fields as $field)
      @if($field['type'] === 'heading')
        <div class="heading-field">{{ $field['label'] }}</div>
      @elseif($field['type'] === 'paragraph')
        <div class="paragraph-field field-block">{{ $field['label'] }}</div>
      @else
        <div class="field-block">
          <div class="field-label">
            {{ $field['label'] }}@if(!empty($field['required']))<span class="field-required">*</span>@endif
          </div>
          @php
            $answer = $field['answer'];
            $displayAnswer = '';
            if (is_array($answer)) {
              $displayAnswer = !empty($answer) ? implode(', ', $answer) : null;
            } elseif (is_bool($answer)) {
              $displayAnswer = $answer ? 'Yes (checked)' : 'No (unchecked)';
            } elseif ($answer !== null && $answer !== '') {
              $displayAnswer = (string) $answer;
            }
          @endphp
          <div class="field-answer{{ $displayAnswer ? '' : ' empty' }}">
            {{ $displayAnswer ?: '(no answer provided)' }}
          </div>
        </div>
      @endif
    @endforeach
  </div>
  @endif

  <!-- Agreement -->
  <div class="section">
    <div class="section-title">Client Agreement</div>
    <div class="agreement-box">
      <div>
        <span class="agreement-check">&#10003;</span>
        <span class="agreement-text">
          I have read and understood the above, and I voluntarily agree to proceed with this session.
        </span>
      </div>
      <div class="agreement-meta">
        Agreed by: <strong>{{ $userName }}</strong> ({{ $userEmail }})<br>
        Date &amp; Time: <strong>{{ $consentedAt }}</strong>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <div class="footer">
    This document is an official record of the client&rsquo;s consent. Generated automatically by {{ $companyName }}.
    &nbsp;|&nbsp; Appointment ID #{{ $appointmentId }} &nbsp;|&nbsp; {{ $generatedAt }}
  </div>

</div>
</body>
</html>
