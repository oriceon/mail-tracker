<?php

namespace OriceOn\MailTracker\Tests;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Event;
use OriceOn\MailTracker\MailTracker;
use OriceOn\MailTracker\RecordBounceJob;
use OriceOn\MailTracker\RecordDeliveryJob;
use OriceOn\MailTracker\RecordComplaintJob;
use OriceOn\MailTracker\RecordLinkClickJob;
use OriceOn\MailTracker\Events\LinkClickedEvent;

class RecordLinkClickJobTest extends SetUpTest
{
    /**
     * @test
     */
    public function it_records_clicks_to_links()
    {
        Event::fake();

        $track = MailTracker::sentEmailModel()->newQuery()->create(['uuid' => Str::uuid()->toString()]);

        $clicks = $track->clicks;
        ++$clicks;

        $redirect = 'http://' . Str::random(15) . '.com/' . Str::random(10) . '/' . Str::random(10) . '/' . rand(0, 100) . '/' . rand(0, 100) . '?page=' . rand(0, 100) . '&x=' . Str::random(32);

        $job = new RecordLinkClickJob($track, $redirect, '127.0.0.1');
        $job->handle();

        Event::assertDispatched(LinkClickedEvent::class, function ($e) use ($track, $redirect) {
            return $track->id === $e->sent_email->id && $e->ip_address === '127.0.0.1' && $e->link_url === $redirect;
        });

        $this->assertDatabaseHas('sent__emails__clicks', [
            'url'    => $redirect,
            'clicks' => 1,
        ]);
    }
}
