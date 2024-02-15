<?php

namespace OriceOn\MailTracker\Events;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;
use OriceOn\MailTracker\Contracts\SentEmailModel;

class TransientBouncedMessageEvent implements ShouldQueue
{
    use SerializesModels;

    public $email_address;

    public $sent_email;

    public $bounce_sub_type;

    public $diagnostic_code;

    /**
     * Create a new event instance.
     *
     * @param string                    $email_address
     * @param null|Model|SentEmailModel $sent_email    $sent_email
     */
    public function __construct($email_address, $bounce_sub_type, $diagnostic_code, null|Model|SentEmailModel $sent_email = null)
    {
        $this->email_address   = $email_address;
        $this->sent_email      = $sent_email;
        $this->bounce_sub_type = $bounce_sub_type;
        $this->diagnostic_code = $diagnostic_code;
    }
}
