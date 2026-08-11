<?php

use ByJG\Config\Config;
use ByJG\Mail\MailerFactory;
use ByJG\Mail\Wrapper\FakeSenderWrapper;
use ByJG\Mail\Wrapper\MailWrapperInterface;

return [

    // Override: Use FakeSenderWrapper for testing
    MailWrapperInterface::class => function () {
        $apiKey = Config::get('EMAIL_CONNECTION');
        MailerFactory::registerMailer(FakeSenderWrapper::class);

        return MailerFactory::create($apiKey);
    },

];
