<?php

namespace OriceOn\MailTracker\Events;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;
use OriceOn\MailTracker\Contracts\SentEmailModel;

class ViewEmailEvent implements ShouldQueue
{
    use SerializesModels;

    public $sent_email;

    public $ip_address;

    /**
     * Create a new event instance.
     */
    public function __construct(Model|SentEmailModel $sent_email, $ip_address)
    {
        $this->sent_email = $sent_email;
        $this->ip_address = $ip_address;
    }
}
