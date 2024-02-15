<?php

namespace OriceOn\MailTracker;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Event;
use OriceOn\MailTracker\Events\LinkClickedEvent;

class RecordLinkClickJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $sentEmail;

    public $url;

    public $ipAddress;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

    public function __construct($sentEmail, $url, $ipAddress)
    {
        $this->sentEmail = $sentEmail;
        $this->url       = $url;
        $this->ipAddress = $ipAddress;
    }

    public function retryUntil()
    {
        return now()->addDays(5);
    }

    public function handle()
    {
        ++$this->sentEmail->clicks;
        $this->sentEmail->save();

        $url_clicked = MailTracker::sentEmailClickModel()->newQuery()
            ->where('sent_email_id', $this->sentEmail->id)
            ->where('url', $this->url)
            ->first();

        if ($url_clicked) {
            ++$url_clicked->clicks;
            $url_clicked->clicked_at = now();
            $url_clicked->save();
        }
        else {
            $url_clicked = MailTracker::sentEmailClickModel()->newQuery()->create([
                'sent_email_id' => $this->sentEmail->id,
                'url'           => $this->url,
                'clicks'        => 1,
                'clicked_at'    => now(),
            ]);
        }

        Event::dispatch(new LinkClickedEvent(
            $this->sentEmail,
            $this->ipAddress,
            $this->url,
        ));
    }
}
