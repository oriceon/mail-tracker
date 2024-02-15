<?php

namespace OriceOn\MailTracker;

use Aws\Sns\Message as SNSMessage;
use Aws\Sns\MessageValidator as SNSMessageValidator;
use GuzzleHttp\Client as Guzzle;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use RuntimeException;

class SNSController extends Controller
{
    public function callback(Request $request)
    {
        if (config('app.env') !== 'production' && $request->message) {
            // phpunit cannot mock static methods so without making a facade
            // for SNSMessage we have to pass the json data in $request->message
            $message = new SNSMessage(json_decode($request->message, true));
        }
        else {
            // get body from request
            $body = $request->getContent();

            // Make sure the SNS-provided header exists.
            $messageType = $request->header('x-amz-sns-message-type');

            if ( ! $messageType) {
                throw new RuntimeException('SNS message type header not provided.');
            }

            $message   = SNSMessage::fromJsonString($body);
            $validator = app(SNSMessageValidator::class);
            $validator->validate($message);
        }

        // If we have a topic defined, make sure this is that topic
        if (config('mail-tracker.sns-topic') && $message->offsetGet('TopicArn') !== config('mail-tracker.sns-topic')) {
            return 'invalid topic ARN';
        }

        switch ($message->offsetGet('Type')) {
            case 'SubscriptionConfirmation':
                return $this->confirm_subscription($message);

            case 'Notification':
                return $this->process_notification($message);
        }
    }

    public function process_bounce($message)
    {
        RecordBounceJob::dispatch($message)
            ->onQueue(config('mail-tracker.tracker-queue'));
    }

    public function process_complaint($message)
    {
        RecordComplaintJob::dispatch($message)
            ->onQueue(config('mail-tracker.tracker-queue'));
    }

    protected function confirm_subscription($message)
    {
        $client = new Guzzle();
        $client->get($message->offsetGet('SubscribeURL'));

        return 'subscription confirmed';
    }

    protected function process_notification($message)
    {
        $message = json_decode($message->offsetGet('Message'));

        switch ($message->notificationType) {
            case 'Delivery':
                $this->process_delivery($message);

                break;

            case 'Bounce':
                $this->process_bounce($message);

                break;

            case 'Complaint':
                $this->process_complaint($message);

                break;
        }

        return 'notification processed';
    }

    protected function process_delivery($message)
    {
        RecordDeliveryJob::dispatch($message)
            ->onQueue(config('mail-tracker.tracker-queue'));
    }
}
