# Laravel Mail Tracker

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]
[![Travis][ico-travis]][link-travis]

Mail Tracker will hook into all outgoing emails from Laravel and inject a tracking code into it. It will also store the rendered email in the database.

## Install

Via Composer

```bash
composer require oriceon/mail-tracker
```

Publish the config file and migration

```bash
php artisan vendor:publish --provider="OriceOn\MailTracker\MailTrackerServiceProvider"
```

Run the migration

```bash
php artisan migrate
```

Note: If you would like to use a different connection to store your models,
you should update the mail-tracker.php config entry `connection` before running the migrations.

If you would like to use your own migrations, you can skip this library migrations by calling `MailTracker::ignoreMigrations()`. For example:

```php
// In AppServiceProvider

public function boot()
{
    MailTracker::ignoreMigrations();
}
```

## Usage

Once installed, all outgoing mail will be logged to the database. The following config options are available in config/mail-tracker.php:

-   **name**: set your App Name.
-   **inject-pixel**: set to true to inject a tracking pixel into all outgoing html emails.
-   **track-links**: set to true to rewrite all anchor href links to include a tracking link. The link will take the user back to your website which will then redirect them to the final destination after logging the click.
-   **expire-days**: How long in days that an email should be retained in your database. If you are sending a lot of mail, you probably want it to eventually expire. Set it to zero to never purge old emails from the database.
-   **route**: The route information for the tracking URLs. Set the prefix and middlware as desired.

If you do not wish to have an email tracked, then you can add the `X-No-Track` header to your message. Put any random string into this header to prevent the tracking from occurring. The header will be removed from the email prior to being sent.

```php
\Mail::send('email.test', [], function ($message) {
    // ... other settings here
    $message->getHeaders()->addTextHeader('X-No-Track', Str::random(10));
});
```

### Storing content of mails in filesystem

By default, the content of an e-mail is stored in the `message` column in the database so that the e-mail can be viewed after it has been sent. 
If a lot of emails are sent, this can consume a lot of memory and slow down the database overall. It is possible to specify in the configuration that the content should be saved to a file in the file system.

````php
    'log-content-strategy' => 'filesystem',
    'tracker-filesystem' => null
    'tracker-filesystem-folder' => 'mail-tracker',
````
To use the filesystem you need to change the `log-content-strategy` from `database` to `filesystem`. 
You can specify the disk with `tracker-filesystem` and the folder it should store the file in with `tracker-filesystem-folder`.

### Overriding models

In some cases you want to override the built-in models. You can do so easily for example in you `AppServiceProvider` with

```php
MailTracker::useSentEmailModel(YourOwnSentEmailModel::class);
MailTracker::useSentEmailClickModel(YourOwnSentEmailClickModel::class);
```

Your model should implement to `SentEmailModel` or `SentEmailClickModel` interface. This package provides traits to easily implement your own models but not have to reimplement or copy existing code.

```php
use Illuminate\Database\Eloquent\Model;
use OriceOn\MailTracker\Concerns\IsSentEmailModel;
use OriceOn\MailTracker\Contracts\SentEmailModel;

class OwnEmailSentModel extends Model implements SentEmailModel {
    use IsSentEmailModel;

    protected static $unguarded = true;

    protected $casts = [
        'meta'       => 'collection',
        'opened_at'  => 'datetime',
        'clicked_at' => 'datetime',
    ];
}
```

## Skip Tracking for Specific Emails

If you have a specific email that you do not want to track, you can add the `X-No-Track` header to the email. This will prevent the email from being tracked. The header will be removed from the email prior to being sent.

In laravel 9+ onwards you can introduce a headers method to your Mailable class. This will stop the tracking pixel/click tracking from applying to the Mailable
```php
public function headers()
{
    return [
        'X-No-Track' => Str::random(10),
    ];
}
```

## Skipping Open/Click Tracking for Anti-virus/Spam Filters

Some mail servers might scan emails before they deliver which can trigger the tracking pixel, or even clicked links. You can add an event listener to the ValidActionEvent to handle this. 

```php
class ValidUserListener {
    public function handle(ValidActionEvent $event)
    {
        if (in_array(request()->userAgent(), ['Mozilla/5.0', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36 Edge/12.246 Mozilla/5.0']) {
            $event->skip = true;
        }
    }
}
```

Ensure you add the listener to the `ValidActionEvent` in your `EventServiceProvider`, if you aren't using automatic event discovery.


## Note on dev testing

Several people have reported the tracking pixel not working while they were testing. What is happening with the tracking pixel is that the email client is connecting to your website to log the view. In order for this to happen, images have to be visible in the client, and the client has to be able to connect to your server.

When you are in a dev environment (i.e. using the `.test` domain with Valet, or another domain known only to your computer) you must have an email client on your computer. Further complicating this is the fact that Gmail and some other web-based email clients don't connect to the images directly, but instead connect via proxy. That proxy won't have a connection to your `.test` domain and therefore will not properly track emails. I always recommend using [mailtrap.io](https://mailtrap.io) for any development environment when you are sending emails. Not only does this solve the issue (mailtrap.io does not use a proxy service to forward images in the emails) but it also protects you from accidentally sending real emails from your test environment.

## Events

When an email is sent, viewed, or a link is clicked, its tracking information is counted in the database using the OriceOn\MailTracker\Model\SentEmail model. This processing is done via dispatched jobs to the queue in order to prevent the database from being overwhelmed in an email blast situation. You may choose the queue that these events are dispatched via the `mail-tracker.tracker-queue` config setting, or leave it `null` to use the default queue. By using a non-default queue, you can prioritize application-critical tasks above these tracking tasks.

You may want to do additional processing on these events, so an event is fired in these cases:

-   OriceOn\MailTracker\Events\EmailSentEvent
    - Public attribute `sent_email` contains the `SentEmail` model
-   OriceOn\MailTracker\Events\ViewEmailEvent
    - Public attribute `sent_email` contains the `SentEmail` model
    - Public attribute `ip_address` contains the IP address that was used to trigger the event
-   OriceOn\MailTracker\Events\LinkClickedEvent
    - Public attribute `sent_email` contains the `SentEmail` model
    - Public attribute `ip_address` contains the IP address that was used to trigger the event
    - Public attribute `link_url` contains the clicked URL

If you are using the Amazon SNS notification system, these events are fired so you can do additional processing.

-   OriceOn\MailTracker\Events\EmailDeliveredEvent (when you received a "message delivered" event, you may want to mark the email as "good" or "delivered" in your database)
    - Public attribute `sent_email` contains the `SentEmail` model
    - Public attribute `email_address` contains the specific address that was used to trigger the event
-   OriceOn\MailTracker\Events\ComplaintMessageEvent (when you received a complaint, ex: marked as "spam", you may want to remove the email from your database)
    - Public attribute `sent_email` contains the `SentEmail` model
    - Public attribute `email_address` contains the specific address that was used to trigger the event
-   OriceOn\MailTracker\Events\PermanentBouncedMessageEvent (when you receive a permanent bounce, you may want to mark the email as bad or remove it from your database)
    OriceOn\MailTracker\Events\TransientBouncedMessageEvent (when you receive a transient bounce.  Check the event's public attributes for `bounce_sub_type` and `diagnostic_code` to determine if you want to do additional processing when this event is received.)
    - Public attribute `sent_email` contains the `SentEmail` model
    - Public attribute `email_address` contains the specific address that was used to trigger the event

To install an event listener, you will want to create a file like the following:

```php
<?php

namespace App\Listeners;

use OriceOn\MailTracker\Events\ViewEmailEvent;

class EmailViewed
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  ViewEmailEvent  $event
     * @return void
     */
    public function handle(ViewEmailEvent $event)
    {
        // Access the model using $event->sent_email
        // Access the IP address that triggered the event using $event->ip_address
    }
}
```

```php
<?php

namespace App\Listeners;

use OriceOn\MailTracker\Events\PermanentBouncedMessageEvent;

class BouncedEmail
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  PermanentBouncedMessageEvent  $event
     * @return void
     */
    public function handle(PermanentBouncedMessageEvent $event)
    {
        // Access the email address using $event->email_address
    }
}
```

Then you must register the events you want to act on in your \App\Providers\EventServiceProvider \$listen array:

```php
/**
 * The event listener mappings for the application.
 *
 * @var array
 */
protected $listen = [
    'OriceOn\MailTracker\Events\EmailSentEvent' => [
        'App\Listeners\EmailSent',
    ],
    'OriceOn\MailTracker\Events\ViewEmailEvent' => [
        'App\Listeners\EmailViewed',
    ],
    'OriceOn\MailTracker\Events\LinkClickedEvent' => [
        'App\Listeners\EmailLinkClicked',
    ],
    'OriceOn\MailTracker\Events\EmailDeliveredEvent' => [
        'App\Listeners\EmailDelivered',
    ],
    'OriceOn\MailTracker\Events\ComplaintMessageEvent' => [
        'App\Listeners\EmailComplaint',
    ],
    'OriceOn\MailTracker\Events\PermanentBouncedMessageEvent' => [
        'App\Listeners\BouncedEmail',
    ],
];
```

### Passing data to the event listeners

Often times you may need to link a sent email to another model. The best way to handle this is to add a header to your outgoing email that you can retrieve in your event listener. Here is an example:

```php
/**
 * Send an email and do processing on a model with the email
 */
\Mail::send('email.test', [], function ($message) use($email, $subject, $name, $model) {
    $message->from('from@johndoe.com', 'From Name');
    $message->sender('sender@johndoe.com', 'Sender Name');
    $message->to($email, $name);
    $message->subject($subject);

    // Create a custom header that we can later retrieve
    $message->getHeaders()->addTextHeader('X-Model-ID',$model->id);
});
```

and then in your event listener:

```
public function handle(EmailSentEvent $event)
{
    $tracker = $event->sent_email;
    $model_id = $event->sent_email->getHeader('X-Model-ID');
    $model = Model::find($model_id);
    // Perform your tracking/linking tasks on $model knowing the SentEmail object
}
```

Note that the headers you are attaching to the email are actually going out with the message, so do not store any data that you wouldn't want to expose to your email recipients.

## Exceptions

The following exceptions may be thrown. You may add them to your ignore list in your exception handler, or handle them as you wish.

-   OriceOn\MailTracker\Exceptions\BadUrlLink - Something went wrong with the url link. Basically, the system could not properly parse the URL link to send the redirect to.

## Amazon SES features

If you use Amazon SES, you can add some additional information to your tracking. To set up the SES callbacks, first set up SES notifications under your domain in the SES control panel. Then subscribe to the topic by going to the admin panel of the notification topic and creating a subscription for the URL you copied from the admin page. The system should immediately respond to the subscription request. If you like, you can use multiple subscriptions (i.e. one for delivery, one for bounces). See above for events that are fired on a failed message. **For added security, it is recommended to set the topic ARN into the mail-tracker config.**

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CONDUCT](CONDUCT.md) for details.

## Security

If you discover any security related issues, please email oriceon@gmail.com instead of using the issue tracker.

## Credits

Thanks to the original author, this is a fwork of: [https://github.com/jdavidbakr/mail-tracker][link-fwork]  

-   [J David Baker][link-author]
-   [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/oriceon/mail-tracker.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/oriceon/mail-tracker/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/oriceon/MailTracker.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/oriceon/MailTracker.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/oriceon/mail-tracker.svg?style=flat-square
[link-packagist]: https://packagist.org/packages/oriceon/mail-tracker
[link-travis]: https://travis-ci.com/oriceon/mail-tracker
[link-scrutinizer]: https://scrutinizer-ci.com/g/oriceon/MailTracker/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/oriceon/MailTracker
[link-downloads]: https://packagist.org/packages/oriceon/mail-tracker
[link-author]: https://github.com/oriceon
[link-contributors]: ../../contributors
[link-fwork]: https://github.com/jdavidbakr/mail-tracker
