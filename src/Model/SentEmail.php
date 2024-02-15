<?php

namespace OriceOn\MailTracker\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use OriceOn\MailTracker\Concerns\IsSentEmailModel;
use OriceOn\MailTracker\Contracts\SentEmailModel;

/**
 * @property string           $uuid
 * @property string           $sender_name
 * @property string           $sender_email
 * @property null|array       $recipient_to
 * @property null|array       $recipient_cc
 * @property null|array       $recipient_bcc
 * @property string           $subject
 * @property string           $message
 * @property string           $headers
 * @property null|Collection  $meta
 * @property null|int         $opens
 * @property null|int         $clicks
 * @property null|string      $opened_at
 * @property null|string      $clicked_at
 * @property null|int         $type
 */
class SentEmail extends Model implements SentEmailModel
{
    use IsSentEmailModel;

    protected $table = 'sent__emails__lists';

    protected $fillable = [
        'uuid',
        'sender_name',
        'sender_email',
        'recipient_to',
        'recipient_cc',
        'recipient_bcc',
        'subject',
        'message',
        'headers',
        'meta',
        'opens',
        'clicks',
        'opened_at',
        'clicked_at',
        'type',
    ];

    protected $casts = [
        'recipient_to'  => 'json',
        'recipient_cc'  => 'json',
        'recipient_bcc' => 'json',
        'meta'          => 'collection',
        'opened_at'     => 'datetime',
        'clicked_at'    => 'datetime',
    ];
}
