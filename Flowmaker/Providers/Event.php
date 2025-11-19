<?php

namespace Modules\Flowmaker\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Flowmaker\Listeners\RespondOnMessage;

class Event extends ServiceProvider
{
    protected $listen = [];

    protected $subscribe = [
        RespondOnMessage::class,
    ];
}