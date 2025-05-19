<?php

declare(strict_types=1);

namespace Casino\Server\Repository;

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
class InMemoryGameRepository implements GameRepositoryInterface
{
    /**
     * @var array<string, GameSessionDTO> In-memory storage for game sessions
     */
    private array $sessions = [];

    /**
     * @var GameConfigDTO Game configuration
     */
    private GameConfigDTO $gameConfig;

    /**
     * @param LoggerInterface $logger Logger for operations logging
     * @param int $reelsCount Number of reels in the game
     * @param int $rowsCount Number of rows in the game
     * @param float $minBet Minimum allowed bet amount
     * @param float $maxBet Maximum allowed bet amount
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly int $reelsCount,
        private readonly int $rowsCount,
        private readonly float $minBet,
        private readonly float $maxBet
    ) {
        // Initialize game configuration with proper signature
        $this->gameConfig = new GameConfigDTO(
            // Table of symbols and their payouts (symbol => coefficient)
            [
                'Cherry' => 10,
                'Lemon' => 20,
                'Orange' => 30,
                'Watermelon' => 40
            ],
            $this->reelsCount,
            $this->rowsCount,
            $this->minBet,
            $this->maxBet,
            []
        );
        
        $this->logger->info('InMemoryGameRepository initialized with configuration', [
            'reelsCount' => $this->reelsCount,
            'rowsCount' => $this->rowsCount,
            'minBet' => $this->minBet,
            'maxBet' => $this->maxBet
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
    public function getSession(string $sessionId): ?GameSessionDTO
    {
        if (!isset($this->sessions[$sessionId])) {
            $this->logger->warning('Session not found', ['sessionId' => $sessionId]);
            return null;
        }
        
        $this->logger->info('Retrieved session', ['sessionId' => $sessionId]);
        return $this->sessions[$sessionId];
    }

    /**
     * {@inheritdoc}
     */
    public function createSession(float $initialBalance): GameSessionDTO
    {
        // Generate a unique session ID
        $sessionId = uniqid('session_', true);
        
        // Create a new session
        $session = new GameSessionDTO(
            $sessionId,
            $initialBalance,
            0.0,
            0.0,
            new \DateTimeImmutable(),
            null,
            true
        );
        
        // Store the session
        $this->sessions[$sessionId] = $session;
        
        $this->logger->info('Created new session', [
            'sessionId' => $sessionId,
            'initialBalance' => $initialBalance
        ]);
        
        return $session;
    }

    /**
     * {@inheritdoc}
     */
    public function updateSession(GameSessionDTO $session): void
    {
        // Update session in storage
        $this->sessions[$session->sessionId] = $session;
        
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
        
        if ($session === null) {
            $this->logger->error('Cannot save spin result - session not found', ['sessionId' => $sessionId]);
            return;
        }
        
        // Update balance and session statistics
        $session->balance = $session->balance - $result->betAmount + $result->winAmount;
        $session->totalBet += $result->betAmount;
        $session->totalWin += $result->winAmount;
        $session->lastActivity = new \DateTimeImmutable();
        
        // Update session in storage
        $this->updateSession($session);
        
        $this->logger->info('Saved spin result', [
            'sessionId' => $sessionId,
            'betAmount' => $result->betAmount,
            'winAmount' => $result->winAmount,
            'newBalance' => $session->balance
        ]);
    }
}
