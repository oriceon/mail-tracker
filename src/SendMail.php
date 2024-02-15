<?php

namespace OriceOn\MailTracker;

use Illuminate\Support\Facades\Mail;

class SendMail
{
    public function __construct(array $mailData) {
        Mail::send(new MailTrackerSend($mailData));
    }
}
