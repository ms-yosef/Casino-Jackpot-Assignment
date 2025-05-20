<?php

declare(strict_types=1);

namespace Casino\Server\Repositories;

use Casino\Server\DTO\GameConfigDTO;
use Casino\Server\DTO\GameSessionDTO;
use Casino\Server\DTO\SpinResultDTO;
use Casino\Server\Interfaces\Repository\GameRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * In-memory implementation of the game repository.
 * 
 * This implementation stores all data in memory during the request lifecycle.
 * Data is not persisted between requests.
 */
class InMemoryGameRepository extends AbstractGameRepository
{
    /**
     * @var array<string, GameSessionDTO> In-memory storage for game sessions
     */
    private static array $sessions = [];

    /**
     * @param LoggerInterface $logger Logger for operations logging
     * @param int $reelsCount Number of reels in the game
     * @param int $rowsCount Number of rows in the game
     * @param float $minBet Minimum allowed bet amount
     * @param float $maxBet Maximum allowed bet amount
     * @param float $initialCredits Initial credits for new sessions
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly int $reelsCount,
        private readonly int $rowsCount,
        private readonly float $minBet,
        private readonly float $maxBet,
        private readonly float $initialCredits = 10.0
    ) {
        // Initialize game configuration using the parent method
        $this->gameConfig = $this->createGameConfig(
            $this->reelsCount,
            $this->rowsCount,
            $this->minBet,
            $this->maxBet
        );

        $this->logger->info('InMemoryGameRepository initialized with configuration', [
            'reelsCount' => $this->reelsCount,
            'rowsCount' => $this->rowsCount,
            'minBet' => $this->minBet,
            'maxBet' => $this->maxBet,
            'initialCredits' => $this->initialCredits
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getGameConfig(): GameConfigDTO
    {
        $this->logger->info('Getting game configuration');
        return $this->gameConfig;
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
        $now = new \DateTimeImmutable();

        $session = new GameSessionDTO(
            $sessionId,
            $initialBalance,
            0.0,
            0.0,
            $now,
            null,
            true
        );

        self::$sessions[$sessionId] = $session;

        $this->logger->info('Created new session', [
            'sessionId' => $sessionId,
            'initialBalance' => $initialBalance
        ]);

        return $session;
    }

    /**
     * {@inheritdoc}
     */
    public function getSession(string $sessionId): ?GameSessionDTO
    {
        if (!isset(self::$sessions[$sessionId])) {
            $this->logger->warning('Session not found', ['sessionId' => $sessionId]);
            return null;
        }

        $session = self::$sessions[$sessionId];
        $session->lastActivity = new \DateTimeImmutable();

        $this->logger->info('Retrieved session', ['sessionId' => $sessionId]);
        return $session;
    }

    /**
     * {@inheritdoc}
     */
    public function updateSession(GameSessionDTO $session): void
    {
        if (!isset(self::$sessions[$session->sessionId])) {
            $this->logger->warning('Session not found for update', ['sessionId' => $session->sessionId]);
            return;
        }

        self::$sessions[$session->sessionId] = $session;

        $this->logger->info('Updated session', [
            'sessionId' => $session->sessionId,
            'balance' => $session->balance
        ]);
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
        $session->lastActivity = new \DateTimeImmutable();

        $this->updateSession($session);

        $this->logger->info('Saved spin result', [
            'sessionId' => $sessionId,
            'betAmount' => $result->betAmount,
            'winAmount' => $result->winAmount,
            'newBalance' => $session->balance
        ]);
    }
}
