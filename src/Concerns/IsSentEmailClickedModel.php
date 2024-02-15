<?php

namespace OriceOn\MailTracker\Concerns;

use OriceOn\MailTracker\MailTracker;

trait IsSentEmailClickedModel
{
    public function getConnectionName()
    {
        $connName = config('mail-tracker.connection');

        return $connName ?: config('database.default');
    }

    public function email()
    {
        return $this->belongsTo(MailTracker::$sentEmailModel, 'sent_email_id');
    }
}
