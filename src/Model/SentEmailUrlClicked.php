<?php

namespace jdavidbakr\MailTracker\Model;

use Illuminate\Database\Eloquent\Model;
use jdavidbakr\MailTracker\Concerns\IsSentEmailUrlClickedModel;
use jdavidbakr\MailTracker\Contracts\SentEmailUrlClickedModel;


class SentEmailUrlClicked extends Model implements SentEmailUrlClickedModel
{
    use IsSentEmailUrlClickedModel;

    protected $table;

    protected $fillable = [
        'sent_email_id',
        'url',
        'hash',
        'clicks',
    ];

    public function __construct() {
        $this->table = config('mail-tracker.sent-emails-url-clicked-table', 'sent_emails_url_clicked');
    }
}
