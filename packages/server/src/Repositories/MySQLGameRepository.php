<?php

declare(strict_types=1);

namespace Casino\Server\Repositories;

use Casino\Server\DTO\GameConfigDTO;
use Casino\Server\DTO\GameSessionDTO;
use Casino\Server\DTO\SpinResultDTO;
use Casino\Server\Interfaces\Repository\GameRepositoryInterface;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * MySQL implementation of the game repository.
 *
 * This implementation stores all data in a MySQL database.
 */
class MySQLGameRepository extends AbstractGameRepository
{
    /**
     * @param LoggerInterface $logger Logger for operations logging
     * @param int $reelsCount Number of reels in the game
     * @param int $rowsCount Number of rows in the game
     * @param float $minBet Minimum allowed bet amount
     * @param float $maxBet Maximum allowed bet amount
     * @param float $initialCredits Initial credits for new sessions
     * @param Connection $connection Database connection
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly int $reelsCount,
        private readonly int $rowsCount,
        private readonly float $minBet,
        private readonly float $maxBet,
        private readonly float $initialCredits = 10.0,
        private readonly Connection $connection
    ) {
        // Initialize game configuration using the parent method
        $this->gameConfig = $this->createGameConfig(
            $this->reelsCount,
            $this->rowsCount,
            $this->minBet,
            $this->maxBet
        );

        $this->logger->info('MySQLGameRepository initialized with configuration', [
            'reelsCount' => $this->reelsCount,
            'rowsCount' => $this->rowsCount,
            'minBet' => $this->minBet,
            'maxBet' => $this->maxBet,
            'initialCredits' => $this->initialCredits
        ]);

        // Ensure database tables exist
        $this->initDatabase();
    }

    /**
     * Initialize database tables if they don't exist
     */
    private function initDatabase(): void
    {
        try {
            // Check if the game_sessions table exists
            $tableExists = $this->connection->executeQuery("
                SELECT 1 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() 
                AND table_name = 'game_sessions'
            ")->fetchOne();

            if (!$tableExists) {
                // Create the game_sessions table
                $this->connection->executeStatement("
                    CREATE TABLE game_sessions (
                        session_id VARCHAR(64) PRIMARY KEY,
                        balance DECIMAL(10, 2) NOT NULL,
                        total_bet DECIMAL(10, 2) NOT NULL,
                        total_win DECIMAL(10, 2) NOT NULL,
                        created_at DATETIME NOT NULL,
                        last_activity DATETIME NULL,
                        is_active TINYINT(1) NOT NULL DEFAULT 1
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");

                $this->logger->info('Created game_sessions table');
            }
        } catch (Exception $e) {
            $this->logger->error('Failed to initialize database', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new RuntimeException('Failed to initialize database: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getGameConfig(): GameConfigDTO
    {
        //$this->logger->info('Getting game configuration');
        $this->logger->debug('Getting game configuration', ['gameConfig' => $this->gameConfig]);
        return $this->gameConfig;
    }

    /**
     * {@inheritdoc}
     */
    public function getSession(string $sessionId): ?GameSessionDTO
    {
        try {
            $data = $this->connection->executeQuery(
                'SELECT * FROM game_sessions WHERE session_id = ?',
                [$sessionId]
            )->fetchAssociative();

            if (!$data) {
                $this->logger->warning('Session not found', ['sessionId' => $sessionId]);
                return null;
            }

            // Update last activity
            $this->connection->executeStatement(
                'UPDATE game_sessions SET last_activity = NOW() WHERE session_id = ?',
                [$sessionId]
            );

            $session = new GameSessionDTO(
                $sessionId,
                (float) $data['balance'],
                (float) $data['total_bet'],
                (float) $data['total_win'],
                new DateTimeImmutable($data['created_at']),
                $data['last_activity'] ? new DateTimeImmutable($data['last_activity']) : null,
                (bool) $data['is_active']
            );

            $this->logger->info('Retrieved session', ['sessionId' => $sessionId]);
            return $session;
        } catch (Exception $e) {
            $this->logger->error('Failed to get session', [
                'sessionId' => $sessionId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createSession(float $initialBalance): GameSessionDTO
    {
        if ($initialBalance <= 0) {
            $initialBalance = $this->initialCredits;
            $this->logger->info('Using default initial balance', ['initialBalance' => $initialBalance]);
        }

        $sessionId = uniqid('session_', true);
        $now = new DateTimeImmutable();

        try {
            $this->connection->executeStatement(
                'INSERT INTO game_sessions (session_id, balance, total_bet, total_win, created_at, is_active) 
                 VALUES (?, ?, ?, ?, ?, ?)',
                [
                    $sessionId,
                    $initialBalance,
                    0.0,
                    0.0,
                    $now->format('Y-m-d H:i:s'),
                    1
                ]
            );

            $session = new GameSessionDTO(
                $sessionId,
                $initialBalance,
                0.0,
                0.0,
                $now,
                null,
                true
            );

            $this->logger->info('Created new session', [
                'sessionId' => $sessionId,
                'initialBalance' => $initialBalance
            ]);

            return $session;
        } catch (Exception $e) {
            $this->logger->error('Failed to create session', [
                'error' => $e->getMessage()
            ]);
            throw new RuntimeException('Failed to create session: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateSession(GameSessionDTO $session): void
    {
        try {
            $this->connection->executeStatement(
                'UPDATE game_sessions SET balance = ?, total_bet = ?, total_win = ?, last_activity = ?, is_active = ? WHERE session_id = ?',
                [
                    $session->balance,
                    $session->totalBet,
                    $session->totalWin,
                    $session->lastActivity?->format('Y-m-d H:i:s'),
                    $session->isActive ? 1 : 0,
                    $session->sessionId
                ]
            );

            $this->logger->info('Updated session', [
                'sessionId' => $session->sessionId,
                'balance' => $session->balance,
                'isActive' => $session->isActive
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to update session', [
                'sessionId' => $session->sessionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new RuntimeException("Failed to update session: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function saveSpinResult(string $sessionId, SpinResultDTO $result): void
    {
        $session = $this->getSession($sessionId);

        if (!$session) {
            $this->logger->warning('Session not found for saving spin result', ['sessionId' => $sessionId]);
            return;
        }

        // Update session balance
        $session->balance = $session->balance - $result->betAmount + $result->winAmount;
        $session->totalBet += $result->betAmount;
        $session->totalWin += $result->winAmount;
        $session->lastActivity = new DateTimeImmutable();

        $this->updateSession($session);

        $this->logger->info('Saved spin result', [
            'sessionId' => $sessionId,
            'betAmount' => $result->betAmount,
            'winAmount' => $result->winAmount,
            'newBalance' => $session->balance
        ]);
    }
}
