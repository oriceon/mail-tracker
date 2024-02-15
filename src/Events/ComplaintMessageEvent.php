<?php

namespace OriceOn\MailTracker\Events;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;
use OriceOn\MailTracker\Contracts\SentEmailModel;

class ComplaintMessageEvent implements ShouldQueue
{
    use SerializesModels;

    public $email_address;

    public $sent_email;

    /**
     * Create a new event instance.
     *
     * @param string $email_address
     */
    public function __construct($email_address, null|Model|SentEmailModel $sent_email = null)
    {
        $this->email_address = $email_address;
        $this->sent_email    = $sent_email;
    }
}
