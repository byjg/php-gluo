<?php

use ByJG\Config\Config;
use ByJG\JinjaPhp\Loader\FileSystemLoader;
use ByJG\Mail\Envelope;
use ByJG\Mail\MailerFactory;
use ByJG\Mail\Wrapper\FakeSenderWrapper;
use ByJG\Mail\Wrapper\MailgunApiWrapper;
use ByJG\Mail\Wrapper\MailWrapperInterface;

return [

    // Mail Configuration
    MailWrapperInterface::class => function () {
        $apiKey = Config::get('EMAIL_CONNECTION');
        MailerFactory::registerMailer(MailgunApiWrapper::class);
        MailerFactory::registerMailer(FakeSenderWrapper::class);

        return MailerFactory::create($apiKey);
    },

    // Mail Envelope Factory
    'MAIL_ENVELOPE' => function ($to, $subject, $template, $mapVariables = []) {
        $body = "";

        $loader = new FileSystemLoader(__DIR__ . "/../../templates/emails", ".html");
        $template = $loader->getTemplate($template);
        $body = $template->render($mapVariables);

        $prefix = "";
        if (Config::definition()->getCurrentEnvironment() != "prod") {
            $prefix = "[" . Config::definition()->getCurrentEnvironment() . "] ";
        }
        return new Envelope(Config::get('EMAIL_TRANSACTIONAL_FROM'), $to, $prefix . $subject, $body, true);
    },

];
