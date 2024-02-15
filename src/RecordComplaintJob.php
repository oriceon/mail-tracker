<?php

namespace OriceOn\MailTracker;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Event;
use OriceOn\MailTracker\Events\ComplaintMessageEvent;

class RecordComplaintJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $message;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

    public function __construct($message)
    {
        $this->message = $message;
    }

    public function retryUntil()
    {
        return now()->addDays(5);
    }

    public function handle()
    {
        $sent_email = MailTracker::sentEmailModel()->newQuery()->where('uuid', $this->message->mail->uuid)->first();

        if ($sent_email) {
            $meta = collect($sent_email->meta);
            $meta->put('complaint', true);
            $meta->put('success', false);
            $meta->put('complaint_time', $this->message->complaint->timestamp);

            if ( ! empty($this->message->complaint->complaintFeedbackType)) {
                $meta->put('complaint_type', $this->message->complaint->complaintFeedbackType);
            }
            $meta->put('sns_message_complaint', $this->message); // append the full message received from SNS to the 'meta' field
            $sent_email->meta = $meta;
            $sent_email->save();

            foreach ($this->message->complaint->complainedRecipients as $recipient) {
                Event::dispatch(new ComplaintMessageEvent($recipient->emailAddress, $sent_email));
            }
        }
    }
}
