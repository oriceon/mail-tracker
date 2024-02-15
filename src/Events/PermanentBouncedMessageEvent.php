<?php

namespace OriceOn\MailTracker\Events;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;
use OriceOn\MailTracker\Contracts\SentEmailModel;

class PermanentBouncedMessageEvent implements ShouldQueue
{
    use SerializesModels;

    public $email_address;

    public $sent_email;

    /**
     * Create a new event instance.
     */
    public function __construct(string $email_address, null|Model|SentEmailModel $sent_email = null)
    {
        $this->email_address = $email_address;
        $this->sent_email    = $sent_email;
    }
}
