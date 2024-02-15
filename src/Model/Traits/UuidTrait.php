<?php

namespace OriceOn\MailTracker\Model\Traits;

use Illuminate\Support\Str;

trait UuidTrait
{
    public static function boot(): void
    {
        // Boot other traits on the Model
        parent::boot();

        /*
         * Listen for the creating event on the user model.
         * Sets the 'uuid' to a UUID using Str::uuid() on the instance being created
         */
        static::creating(static function($model) {
            if ( ! $model->uuid) {
                $model->setAttribute('uuid', Str::uuid()->toString());
            }
        });
    }
}
