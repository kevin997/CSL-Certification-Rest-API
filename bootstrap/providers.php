<?php

use Mailjet\LaravelMailjet\MailjetServiceProvider;

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
    App\Providers\JetstreamServiceProvider::class,
    MailjetServiceProvider::class,
];
