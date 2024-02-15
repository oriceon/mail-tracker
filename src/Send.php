<?php

namespace OriceOn\MailTracker;

use Illuminate\Support\Facades\Mail;

class Send
{
    public function __construct(array $mailData) {
        Mail::send(new MailTrackerSend($mailData));
    }
}
