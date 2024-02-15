<?php

namespace OriceOn\MailTracker\Tests;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Event;
use OriceOn\MailTracker\MailTracker;
use OriceOn\MailTracker\RecordBounceJob;
use OriceOn\MailTracker\RecordDeliveryJob;
use OriceOn\MailTracker\RecordTrackingJob;
use OriceOn\MailTracker\RecordComplaintJob;
use OriceOn\MailTracker\RecordLinkClickJob;
use OriceOn\MailTracker\Events\ViewEmailEvent;
use OriceOn\MailTracker\Events\LinkClickedEvent;

class RecordTrackingJobTest extends SetUpTest
{
    /**
     * @test
     */
    public function it_records_views()
    {
        Event::fake();
        $track = MailTracker::sentEmailModel()->newQuery()->create(['uuid' => Str::uuid()->toString()]);

        $job = new RecordTrackingJob($track, '127.0.0.1');
        $job->handle();

        Event::assertDispatched(ViewEmailEvent::class, function ($e) use ($track) {
            return $track->id == $e->sent_email->id && $e->ip_address == '127.0.0.1';
        });

        $this->assertDatabaseHas('sent__emails__lists', [
            'id'    => $track->id,
            'opens' => 1,
        ]);
    }
}
