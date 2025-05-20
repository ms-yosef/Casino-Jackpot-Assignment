<?php

declare(strict_types=1);

use Casino\Server\Factories\DefaultGameFactory;
use Casino\Server\Interfaces\Factory\GameFactoryInterface;
use Casino\Server\Interfaces\Repository\GameRepositoryInterface;
use Casino\Server\Interfaces\Service\GameServiceInterface;
use Casino\Server\Repositories\MySQLGameRepository;
use Casino\Server\Services\DefaultGameService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
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

    // Database connection
    Connection::class => function (ContainerInterface $container) {
        $logger = $container->get(LoggerInterface::class);
        
        try {
            $connectionParams = [
                'dbname' => getenv('DB_DATABASE') ?: 'casino_jackpot',
                'user' => getenv('DB_USERNAME') ?: 'casino_user',
                'password' => getenv('DB_PASSWORD') ?: '$haHaR!',
                'host' => getenv('DB_HOST') ?: 'localhost',
                'port' => getenv('DB_PORT') ?: 3306,
                'driver' => 'pdo_mysql',
                'charset' => 'utf8mb4',
            ];
            
            $connection = DriverManager::getConnection($connectionParams);
            
            // Test the connection
            $connection->connect();
            $logger->info('Database connection established successfully');
            
            return $connection;
        } catch (\Exception $e) {
            $logger->error('Failed to establish database connection', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // If we can't connect to the database, we'll throw an exception
            throw new \RuntimeException('Failed to establish database connection: ' . $e->getMessage(), 0, $e);
        }
    },

    // Application settings
    'settings' => [
        'displayErrorDetails' => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN),
        'logErrors' => true,
        'logErrorDetails' => true,
        'game' => [
            'reelsCount' => getenv('GAME_REELS_COUNT') ?: 3,
            'rowsCount' => getenv('GAME_ROWS_COUNT') ?: 1,
            'minBet' => (float)(getenv('GAME_MIN_BET') ?: 1.0),
            'maxBet' => (float)(getenv('GAME_MAX_BET') ?: 5.0),
            'initialCredits' => (float)(getenv('GAME_INITIAL_CREDITS') ?: 10.0),
            'spinCost' => (float)(getenv('GAME_SPIN_COST') ?: 1.0),
            'symbolsSettings' => json_decode(getenv('GAME_SYMBOLS_SETTINGS') ?: '{}', true),
            'cheatEnabled' => filter_var(getenv('GAME_CHEAT_ENABLED') ?: false, FILTER_VALIDATE_BOOLEAN),
            'cheatConfig' => json_decode(getenv('GAME_CHEAT_CONFIG') ?: '{}', true),
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
        return new MySQLGameRepository(
            $container->get(LoggerInterface::class),
            $settings['reelsCount'],
            $settings['rowsCount'],
            $settings['minBet'],
            $settings['maxBet'],
            $settings['initialCredits'] ?? 10.0,
            $container->get(Connection::class)
        );
    },

    // Game Service
    GameServiceInterface::class => function (ContainerInterface $container) {
        $settings = $container->get('settings')['game'];
        return new DefaultGameService(
            $container->get(GameRepositoryInterface::class),
            $container->get(GameFactoryInterface::class),
            $container->get(LoggerInterface::class),
            $settings['cheatEnabled'] ?? true,
            $settings['cheatConfig'] ?? ['thresholds' => [40, 60], 'chances' => [30, 60]]
        );
    },
];