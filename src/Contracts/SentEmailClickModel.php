<?php

namespace OriceOn\MailTracker\Contracts;

interface SentEmailClickModel
{
    public function getConnectionName();

    public function email();
}
