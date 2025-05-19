<?php

declare(strict_types=1);

use Casino\Server\Factory\DefaultGameFactory;
use Casino\Server\Interfaces\Factory\GameFactoryInterface;
use Casino\Server\Interfaces\Repository\GameRepositoryInterface;
use Casino\Server\Interfaces\Service\GameServiceInterface;
use Casino\Server\Repository\InMemoryGameRepository;
use Casino\Server\Services\DefaultGameService;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

return [
    // Logger
    LoggerInterface::class => function (ContainerInterface $container) {
        // Define log directory
        $logDir = dirname(__DIR__, 2) . '/logs';
        
        // Create log directory if it doesn't exist
        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0755, true) && !is_dir($logDir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $logDir));
            }
        }
        
        $logger = new Logger('casino-jackpot');
        $logger->pushHandler(new RotatingFileHandler($logDir . '/app.log', 7, Level::Debug));
        $logger->pushHandler(new StreamHandler('php://stdout', Level::Debug));
        
        return $logger;
    },

    // Application settings
    'settings' => [
        'displayErrorDetails' => $_ENV['APP_DEBUG'] ?? false,
        'logErrors' => true,
        'logErrorDetails' => true,
        'game' => [
            'reelsCount' => $_ENV['GAME_REELS_COUNT'] ?? 3,
            'rowsCount' => $_ENV['GAME_ROWS_COUNT'] ?? 1,
            'minBet' => (float)($_ENV['GAME_MIN_BET'] ?? 1.0),
            'maxBet' => (float)($_ENV['GAME_MAX_BET'] ?? 5.0),
            'initialCredits' => (float)($_ENV['GAME_INITIAL_CREDITS'] ?? 10.0),
            'spinCost' => (float)($_ENV['GAME_SPIN_COST'] ?? 1.0),
            'symbolsSettings' => json_decode($_ENV['GAME_SYMBOLS_SETTINGS'] ?? '{}', true),
            'cheatEnabled' => (bool)($_ENV['GAME_CHEAT_ENABLED'] ?? false),
            'cheatConfig' => json_decode($_ENV['GAME_CHEAT_CONFIG'] ?? '{}', true),
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
            $settings['maxBet'],
            $settings['symbolsSettings'] ?? null,
            $settings['initialCredits'] ?? 10.0
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
            $settings['maxBet'],
            $settings['initialCredits'] ?? 10.0
        );
    },

    // Game Service
    GameServiceInterface::class => function (ContainerInterface $container) {
        $settings = $container->get('settings')['game'];
        return new DefaultGameService(
            $container->get(GameRepositoryInterface::class),
            $container->get(GameFactoryInterface::class),
            $container->get(LoggerInterface::class),
            $settings['cheatEnabled'] ?? false,
            $settings['cheatConfig'] ?? ['thresholds' => [40, 60], 'chances' => [30, 60]]
        );
    },
];