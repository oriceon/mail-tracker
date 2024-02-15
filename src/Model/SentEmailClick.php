<?php

namespace OriceOn\MailTracker\Model;

use Illuminate\Database\Eloquent\Model;
use OriceOn\MailTracker\Concerns\IsSentEmailClickedModel;
use OriceOn\MailTracker\Contracts\SentEmailClickModel;
use OriceOn\MailTracker\Model\Traits\UuidTrait;

class SentEmailClick extends Model implements SentEmailClickModel
{
    use IsSentEmailClickedModel;
    use UuidTrait;

    protected $table = 'sent__emails__clicks';

    protected $fillable = [
        'uuid',
        'sent_email_id',
        'url',
        'clicks',
        'clicked_at',
    ];

    protected $casts = [
        'clicked_at' => 'datetime',
    ];
}
