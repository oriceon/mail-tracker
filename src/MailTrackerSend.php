<?php

namespace OriceOn\MailTracker;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class MailTrackerSend extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public array $mailData) {}

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build(): static
    {
        $this->from($this->mailData['from']['email'], $this->mailData['from']['name']);

        if (isset($this->mailData['replyTo']['email'], $this->mailData['replyTo']['name'])) {
            $this->replyTo($this->mailData['replyTo']['email'], $this->mailData['replyTo']['name']);
        }

        $this->subject($this->mailData['subject'])->html($this->mailData['message']);

        $this->to($this->mailData['to']['email'], $this->mailData['to']['name']);

        foreach (['cc', 'bcc'] as $type) {
            if (isset($this->mailData[$type]) && is_array($this->mailData[$type]) && count($this->mailData[$type])) {
                foreach ($this->mailData[$type] as $recipient) {
                    if (isset($recipient['name'], $recipient['email'])) {
                        $this->{$type}(new Address($recipient['email'], $recipient['name']));
                    }
                }
            }
        }

        if (isset($this->mailData['headers']) && is_array($this->mailData['headers']) && count($this->mailData['headers'])) {
            $this->withSymfonyMessage(function(Email $message) {
                foreach ($this->mailData['headers'] as $headerKey => $headerValue) {
                    $message->getHeaders()->addTextHeader($headerKey, $headerValue);
                }
            });
        }

        return $this;
    }
}
