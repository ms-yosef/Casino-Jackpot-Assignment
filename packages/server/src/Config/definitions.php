<?php

declare(strict_types=1);

use Casino\Server\Factory\DefaultGameFactory;
use Casino\Server\Interfaces\Factory\GameFactoryInterface;
use Casino\Server\Interfaces\Repository\GameRepositoryInterface;
use Casino\Server\Interfaces\Service\GameServiceInterface;
use Casino\Server\Repository\InMemoryGameRepository;
use Casino\Server\Services\DefaultGameService;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

return [
    // Logger
    LoggerInterface::class => function (ContainerInterface $container) {
        $logger = new Logger('casino-jackpot');
        $logger->pushHandler(new StreamHandler('php://stdout', 100));
        return $logger;
    },

    // Application settings
    'settings' => [
        'displayErrorDetails' => $_ENV['APP_DEBUG'] ?? false,
        'logErrors' => true,
        'logErrorDetails' => true,
        'game' => [
            'reelsCount' => $_ENV['GAME_REELS_COUNT'] ?? 3,
            'rowsCount' => $_ENV['GAME_ROWS_COUNT'] ?? 3,
            'minBet' => (float)($_ENV['GAME_MIN_BET'] ?? 1.0),
            'maxBet' => (float)($_ENV['GAME_MAX_BET'] ?? 5.0),
        ],
    ],

    // Game Factory
    GameFactoryInterface::class => function (ContainerInterface $container) {
        $settings = $container->get('settings')['game'];
        return new DefaultGameFactory(
            $container->get(LoggerInterface::class),
            $settings['reelsCount'],
            $settings['rowsCount'],
            $settings['minBet'],
            $settings['maxBet']
        );
    },

    // Game Repository
    GameRepositoryInterface::class => function (ContainerInterface $container) {
        $settings = $container->get('settings')['game'];
        return new InMemoryGameRepository(
            $container->get(LoggerInterface::class),
            $settings['reelsCount'],
            $settings['rowsCount'],
            $settings['minBet'],
            $settings['maxBet']
        );
    },

    // Game Service
    GameServiceInterface::class => function (ContainerInterface $container) {
        return new DefaultGameService(
            $container->get(GameRepositoryInterface::class),
            $container->get(GameFactoryInterface::class),
            $container->get(LoggerInterface::class)
        );
    },
];