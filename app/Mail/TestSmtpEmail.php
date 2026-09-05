<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TestSmtpEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function build()
    {
        return $this->subject('SMTP Configuration Test Email')
            ->text('emails.test-smtp'); // Create a Blade view
    }
}
