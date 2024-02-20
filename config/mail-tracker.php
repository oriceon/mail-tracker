<?php

return [
    /*
     * To disable the pixel injection, set this to false.
     */
    'inject-pixel' => true,

    /*
     * To disable injecting tracking links, set this to false.
     */
    'track-links' => true,

    /*
     * Optionally expire old emails, set to 0 to keep forever.
     */
    'expire-days' => 0,

    /*
     * Where should the ping-back URL route be?
     */
    'route' => [
        'prefix'     => 'email',
        'middleware' => ['api'],
    ],

    /*
     * If we get a link to click without a URL, where should we send it to?
     */
    'redirect-missing-links-to' => '/',

    /*
     * Default database connection name (optional - use null for default)
     */
    'connection' => null,

    /*
     * The SNS notification topic - if set, discard all notifications not in this topic.
     */
    'sns-topic' => null,

    /*
     * Determines whether the body of the email is logged or not
     */
    'log-message' => true,

    /*
     * Determines whether the message body should be stored in a file instead of database
     * Can be either 'database' or 'filesystem'
     */
    'log-message-strategy' => 'database',

    /*
     * What filesystem we use for storing html files
     */
    'tracker-filesystem'        => null,
    'tracker-filesystem-folder' => 'mail-tracker',

    /*
     * What queue should we dispatch our tracking jobs to?  Null will use the default queue.
     */
    'tracker-queue' => null,

    /*
     * Length of time to default past email search - if set, will set the default past limit to the amount of days below (Ex: => 356)
     */
    'search-date-start' => null,
];
