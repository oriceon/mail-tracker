<?php

namespace OriceOn\MailTracker;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OriceOn\MailTracker\Contracts\SentEmailModel;
use OriceOn\MailTracker\Events\EmailSentEvent;
use OriceOn\MailTracker\Model\SentEmail;
use OriceOn\MailTracker\Model\SentEmailClick;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\Multipart\AlternativePart;
use Symfony\Component\Mime\Part\Multipart\MixedPart;
use Symfony\Component\Mime\Part\Multipart\RelatedPart;
use Symfony\Component\Mime\Part\TextPart;

class MailTracker
{
    // Set this to "false" to skip this library migrations
    public static $runsMigrations = true;

    /**
     * The SentEmail model class name.
     */
    public static string $sentEmailModel = SentEmail::class;

    /**
     * The SentEmailClick model class name.
     */
    public static string $sentEmailClickModel = SentEmailClick::class;

    protected $uuid;

    // Allow developers to provide their own
    protected Closure $messageIdResolver;

    /**
     * Configure this library to not register its migrations.
     *
     * @return static
     */
    public static function ignoreMigrations()
    {
        static::$runsMigrations = false;

        return new static();
    }

    /**
     * Set class name of SentEmail model.
     */
    public static function useSentEmailModel(string $sentEmailModelClass): void
    {
        static::$sentEmailModel = $sentEmailModelClass;
    }

    /**
     * Create new SentEmail model.
     */
    public static function sentEmailModel(array $attributes = []): Model|SentEmail
    {
        return new static::$sentEmailModel($attributes);
    }

    /**
     * Set class name of SentEmailClick model.
     */
    public static function useSentEmailClickModel(string $class): void
    {
        static::$sentEmailClickModel = $class;
    }

    /**
     * Create new SentEmailClick model.
     */
    public static function sentEmailClickModel(array $attributes = []): Model|SentEmailClick
    {
        return new static::$sentEmailClickModel($attributes);
    }

    /**
     * Inject the tracking code into the message.
     */
    public function messageSending(MessageSending $event)
    {
        $message = $event->message;

        // Create the trackers
        $this->createTrackers($message);

        // Purge old records
        $this->purgeOldRecords();
    }

    public function messageSent(MessageSent $event): void
    {
        /*
        $sentMessage = $event->sent;
        $headers     = $sentMessage->getOriginalMessage()->getHeaders();
        $uuid        = optional($headers->get('X-Mailer-Uuid'))->getBody();
        $sentEmail   = MailTracker::sentEmailModel()->newQuery()->where('uuid', $uuid)->first();

        if ($sentEmail) {
            $sentEmail->message_id = $this->callMessageIdResolverUsing($sentMessage);
            $sentEmail->save();
        }
        */
    }

    public function getMessageIdResolver(): Closure
    {
        if ( ! isset($this->messageIdResolver)) {
            $this->resolveMessageIdUsing($this->getDefaultMessageIdResolver());
        }

        return $this->messageIdResolver;
    }

    public function resolveMessageIdUsing(Closure $resolver): self
    {
        $this->messageIdResolver = $resolver;

        return $this;
    }

    /**
     * Legacy function.
     *
     * @param [type] $url
     *
     * @return bool
     */
    public static function hash_url($url)
    {
        return str_replace('/', '$', base64_encode($url));
    }

    protected function getDefaultMessageIdResolver(): Closure
    {
        return function(SentMessage $message) {
            /** @var \Symfony\Component\Mime\Header\Headers $headers */
            $headers = $message->getOriginalMessage()->getHeaders();

            // Laravel supports multiple mail drivers.
            // We try to guess if this email was sent using SES
            if ($messageHeader = $headers->get('X-SES-Message-ID')) {
                return $messageHeader->getBody();
            }

            // Second attempt, get the default message ID from symfony mailer
            return $message->getMessageId();
        };
    }

    protected function callMessageIdResolverUsing(SentMessage $message): string
    {
        return $this->getMessageIdResolver()(...func_get_args());
    }

    protected function addTrackers($html, $uuid)
    {
        if (config('mail-tracker.inject-pixel')) {
            $html = $this->injectTrackingPixel($html, $uuid);
        }

        if (config('mail-tracker.track-links')) {
            $html = $this->injectLinkTracker($html, $uuid);
        }

        return $html;
    }

    protected function injectTrackingPixel($html, $uuid)
    {
        // Append the tracking url
        $tracking_pixel = '<img src="' . route('mailTracker_t', [$uuid]) . '" border=0 width=1 height=1 alt="" />';

        $linebreak = Str::random(32);
        $html      = str_replace("\n", $linebreak, $html);

        if (preg_match('/^(.*<body[^>]*>)(.*)$/', $html, $matches)) {
            $html = $matches[1] . $matches[2] . $tracking_pixel;
        }
        else {
            $html = $html . $tracking_pixel;
        }

        return str_replace($linebreak, "\n", $html);
    }

    protected function injectLinkTracker($html, $uuid)
    {
        $this->uuid = $uuid;

        return preg_replace_callback('/(<a[^>]*href=["])([^"]*)/', [$this, 'inject_link_callback'], $html);
    }

    protected function inject_link_callback($matches)
    {
        if (empty($matches[2])) {
            $url = app()->make('url')->to('/');
        }
        else {
            $url = str_replace('&amp;', '&', $matches[2]);
        }

        return $matches[1] . route('mailTracker_n', ['u' => $this->uuid, 'l' => $url]);
    }

    /**
     * Create the trackers.
     */
    protected function createTrackers(Email $message)
    {
        foreach ($message->getTo() as $to_address) {
            foreach ($message->getFrom() as $from_address) {
                $headers = $message->getHeaders();

                // Don't track this email
                if ($headers->get('X-No-Track')) {
                    continue;
                }

                $uuid = Str::uuid()->toString();

                $headers->addTextHeader('X-Mailer-Uuid', $uuid);

                $original_content = $message->getBody();
                $original_html    = '';

                if (
                    ($original_content instanceof (AlternativePart::class)) ||
                    ($original_content instanceof (MixedPart::class)) ||
                    ($original_content instanceof (RelatedPart::class))
                ) {
                    $message_body = $message->getBody() ?: [];
                    $new_parts    = [];

                    foreach ($message_body->getParts() as $part) {
                        if ($part->getMediaSubtype() === 'html') {
                            $original_html = $part->getBody();
                            $new_parts[]   = new TextPart(
                                $this->addTrackers($original_html, $uuid),
                                $message->getHtmlCharset(),
                                $part->getMediaSubtype(),
                                null
                            );
                        }
                        elseif ($part->getMediaSubtype() === 'alternative') {
                            if (method_exists($part, 'getParts')) {
                                foreach ($part->getParts() as $p) {
                                    if ($p->getMediaSubtype() === 'html') {
                                        $original_html = $p->getBody();
                                        $new_parts[]   = new TextPart(
                                            $this->addTrackers($original_html, $uuid),
                                            $message->getHtmlCharset(),
                                            $p->getMediaSubtype(),
                                            null
                                        );

                                        break;
                                    }
                                }
                            }
                        }
                        else {
                            $new_parts[] = $part;
                        }
                    }

                    $message->setBody(new (get_class($original_content))(...$new_parts));
                }
                else {
                    $original_html = $original_content->getBody();

                    if ($original_content->getMediaSubtype() === 'html') {
                        $message->setBody(
                            new TextPart(
                                $this->addTrackers($original_html, $uuid),
                                $message->getHtmlCharset(),
                                $original_content->getMediaSubtype(),
                                null
                            )
                        );
                    }
                }


                $recipient_cc = [];

                foreach ($message->getCc() as $cc_address) {
                    $recipient_cc[] = ['name' => $cc_address->getName(), 'email' => $cc_address->getAddress()];
                }

                $recipient_bcc = [];

                foreach ($message->getBcc() as $bcc_address) {
                    $recipient_bcc[] = ['name' => $bcc_address->getName(), 'email' => $bcc_address->getAddress()];
                }


                /** @var SentEmail $tracker */
                $create = [
                    'uuid'          => $uuid,
                    'sender_name'   => $from_address->getName(),
                    'sender_email'  => $from_address->getAddress(),
                    'recipient_to'  => [['name' => $to_address->getName(), 'email' => $to_address->getAddress()]],
                    'recipient_cc'  => (count($recipient_cc) ? $recipient_cc : null),
                    'recipient_bcc' => (count($recipient_bcc) ? $recipient_bcc : null),
                    'subject'       => $message->getSubject(),
                    'headers'       => $headers->toString(),
                    'opens'         => 0,
                    'clicks'        => 0,
                ];

                if ($columns = $headers->get('X-Columns')) {
                    $columns_string = $columns->getBodyAsString();

                    if (
                        is_string($columns_string) &&
                        (
                            is_object(json_decode($columns_string)) ||
                            is_array(json_decode($columns_string))
                        )
                    ) {
                        $columns_array = json_decode($columns_string);
                        foreach ($columns_array as $column_key => $column_value) {
                            $create[$column_key] = $column_value;
                        }
                    }

                    $db_headers = $message->getHeaders();
                    $db_headers->remove('X-Columns');

                    $create['headers'] = $db_headers->toString();
                }

                $tracker = tap(MailTracker::sentEmailModel($create), function(Model|SentEmailModel $sentEmail) use ($original_html, $uuid) {
                    $sentEmail->fillMessage($original_html, $uuid);
                    $sentEmail->save();
                });

                Event::dispatch(new EmailSentEvent($tracker));
            }
        }

        // Remove this header if it was set
        $headers = $message->getHeaders();
        $headers->remove('X-No-Track');
        $headers->remove('X-Columns');
    }

    /**
     * Purge old records in the database.
     */
    protected function purgeOldRecords()
    {
        if (config('mail-tracker.expire-days') > 0) {
            MailTracker::sentEmailModel()->newQuery()
            ->select('id', 'meta')
            ->where('created_at', '<', now()->subDays(config('mail-tracker.expire-days')))
            ->chunk(10000, function($emails) {
                $ids = [];

                foreach ($emails as $email) {
                    // remove files
                    if ($email->meta && ($filePath = $email->meta->get('message_file_path'))) {
                        Storage::disk(config('mail-tracker.tracker-filesystem'))->delete($filePath);
                    }

                    $ids[] = $email->id;
                }

                MailTracker::sentEmailModel()->newQuery()->whereIn('id', $ids)->delete();
            });
        }
    }
}
