<?php

namespace OriceOn\MailTracker\Concerns;

use Exception;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OriceOn\MailTracker\Contracts\SentEmailModel;
use OriceOn\MailTracker\MailTracker;

trait IsSentEmailModel
{
    public static function bootIsSentEmailModel()
    {
        static::deleting(function(Model|SentEmailModel $email) {
            if ($filePath = $email->meta?->get('message_file_path')) {
                Storage::disk(config('mail-tracker.tracker-filesystem'))->delete($filePath);
            }
        });
    }

    public function getConnectionName()
    {
        $connName = config('mail-tracker.connection');

        return $connName ?: config('database.default');
    }

    /**
     * Returns a bootstrap class about the success/failure of the message.
     *
     * @return [type] [description]
     */
    public function getReportClassAttribute()
    {
        if ( ! empty($this->meta) && $this->meta->has('success')) {
            if ($this->meta->get('success')) {
                return 'success';
            }

            return 'danger';
        }

        return '';
    }

    /**
     * Returns the smtp detail for this message ().
     *
     * @return [type] [description]
     */
    public function getSmtpInfoAttribute()
    {
        if (empty($this->meta)) {
            return '';
        }
        $meta      = $this->meta;
        $responses = [];

        if ($meta->has('smtpResponse')) {
            $response     = $meta->get('smtpResponse');
            $delivered_at = $meta->get('delivered_at');
            $responses[]  = $response . ' - Delivered ' . $delivered_at;
        }

        if ($meta->has('failures')) {
            foreach ($meta->get('failures') as $failure) {
                if ( ! empty($failure['status'])) {
                    $responses[] = $failure['status'] . ' (' . $failure['action'] . '): ' . $failure['diagnosticCode'] . ' (' . $failure['emailAddress'] . ')';
                }
                else {
                    $responses[] = 'Generic Failure (' . $failure['emailAddress'] . ')';
                }
            }
        }
        elseif ($meta->has('complaint')) {
            $complaint_time = $meta->get('complaint_time');

            if ($meta->get('complaint_type')) {
                $responses[] = 'Complaint: ' . $meta->get('complaint_type') . ' at ' . $complaint_time;
            }
            else {
                $responses[] = 'Complaint at ' . $complaint_time;
            }
        }

        return implode(' | ', $responses);
    }

    /**
     * Get message according to log-message-strategy.
     */
    public function getMessageAttribute(): ?string
    {
        if ($message = $this->attributes['message']) {
            return $message;
        }

        if ($this->meta?->has('message_file_path')) {
            if ($messageFilePath = $this->meta->get('message_file_path')) {
                try {
                    return Storage::disk(config('mail-tracker.tracker-filesystem'))->get($messageFilePath);
                }
                catch (FileNotFoundException $e) {
                    return null;
                }
            }
        }

        return null;
    }

    /**
     * Returns a collection of all headers requested from our stored header info.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getAllHeaders()
    {
        return collect(preg_split("/(\r\n)(?!\\s)/", $this->headers))
            ->filter(function($header) {
                return preg_match('/:/', $header);
            })
            ->transform(function($header) {
                $header        = Str::replace("\r\n", '', $header);
                [$key, $value] = explode(':', $header, 2);

                return collect([
                    'key'   => trim($key),
                    'value' => trim($value),
                ]);
            })->filter(function($header) {
                return $header->get('key');
            })->keyBy('key')
            ->transform(function($header) {
                return $header->get('value');
            });
    }

    /**
     * Returns the header requested from our stored header info.
     *
     * @param mixed $key
     */
    public function getHeader($key)
    {
        return $this->getAllHeaders()->get($key);
    }

    public function urlClicks()
    {
        return $this->hasMany(MailTracker::$sentEmailClickModel);
    }

    public function fillMessage(string $originalHtml, string $hash)
    {
        $logMessage = config('mail-tracker.log-message', true);

        if ( ! $logMessage) {
            return;
        }

        $logMessageStrategy = config('mail-tracker.log-message-strategy', 'database');

        if ( ! in_array($logMessageStrategy, ['database', 'filesystem'])) {
            return;
        }

        // handling filesystem strategy
        if ($logMessageStrategy === 'filesystem') {
            // store body in html file
            $basePath        = config('mail-tracker.tracker-filesystem-folder', 'mail-tracker');
            $fileSystem      = config('mail-tracker.tracker-filesystem');
            $messageFilePath = $basePath . '/' . now()->format('Y-m-d') . '/' . $hash . '.html';

            try {
                Storage::disk($fileSystem)->put($messageFilePath, $originalHtml);
            }
            catch (Exception $e) {
                Log::warning($e->getMessage());
                // fail silently
            }

            $meta = collect($this->meta);
            $meta->put('message_file_path', $messageFilePath);
            $this->meta = $meta;
        }
        else {
            $this->message = $originalHtml;
        }
    }
}