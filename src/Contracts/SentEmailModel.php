<?php

namespace OriceOn\MailTracker\Contracts;

interface SentEmailModel
{
    public function getConnectionName();

    public function getAllHeaders();

    public function getHeader(string $key);

    public function fillMessage(string $originalHtml, string $hash);

    public function urlClicks();
}
