<?php

namespace App\Jobs;

use App\Mail\TemplatedMail;
use App\Services\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendTemplatedEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private string $toEmail,
        private string $emailSubject,
        private string $bodyHtml,
        private array  $settings,
        private array  $smtpConfig,
    ) {}

    public function handle(): void
    {
        // Apply SMTP config resolved at dispatch time — no DB access needed here
        EmailService::applySmtpConfig($this->smtpConfig);
        Mail::to($this->toEmail)->send(new TemplatedMail($this->emailSubject, $this->bodyHtml, $this->settings));
    }
}
