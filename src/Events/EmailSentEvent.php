<?php

namespace OriceOn\MailTracker\Events;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;
use OriceOn\MailTracker\Contracts\SentEmailModel;

class EmailSentEvent implements ShouldQueue
{
    use SerializesModels;

    public $sent_email;

    /**
     * Create a new event instance.
     */
    public function __construct(Model|SentEmailModel $sent_email)
    {
        $this->sent_email = $sent_email;
    }
}
