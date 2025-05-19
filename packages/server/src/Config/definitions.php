<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

return [
    LoggerInterface::class => function (ContainerInterface $container) {
        $logger = new Logger('casino-jackpot');
        $logger->pushHandler(new StreamHandler('php://stdout', 100));
        return $logger;
    },

    'settings' => [
        'displayErrorDetails' => $_ENV['APP_DEBUG'] ?? false,
        'logErrors' => true,
        'logErrorDetails' => true,
    ],
];