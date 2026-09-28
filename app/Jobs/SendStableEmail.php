<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** One stable email (to an admin or an owner), logged like every other notification. Never throws. */
class SendStableEmail implements ShouldQueue
{
    use Dispatchable;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public readonly string $to, public readonly Mailable $mail, public readonly string $type) {}

    public function handle(): void
    {
        try {
            Mail::to($this->to)->send($this->mail);
            $this->log('sent', $this->mail->envelope()->subject);
        } catch (Throwable $e) {
            report($e);
            $this->log('failed', error: $e->getMessage());
        }
    }

    private function log(string $status, ?string $message = null, ?string $error = null): void
    {
        NotificationLog::create([
            'channel' => 'email',
            'type' => $this->type,
            'recipient' => $this->to,
            'status' => $status,
            'message' => $message,
            'error' => $error,
        ]);
    }
}
