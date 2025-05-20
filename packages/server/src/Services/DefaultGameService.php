<?php

declare(strict_types=1);

namespace Casino\Server\Services;

use Casino\Server\DTO\CashoutResultDTO;
use Casino\Server\DTO\GameConfigDTO;
use Casino\Server\DTO\GameSessionDTO;
use Casino\Server\DTO\SpinRequestDTO;
use Casino\Server\DTO\SpinResultDTO;
use Casino\Server\Interfaces\Factory\GameFactoryInterface;
use Casino\Server\Interfaces\Repository\GameRepositoryInterface;
use Casino\Server\Interfaces\Service\GameServiceInterface;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Random\RandomException;

/**
 * Default implementation of the game service.
 *
 * This service is responsible for handling game operations and business logic.
 */
readonly class DefaultGameService implements GameServiceInterface
{
    /**
     * Default thresholds for balance categories (low, medium, high)
     */
    private const array DEFAULT_BALANCE_THRESHOLDS = [40, 60];
    
    /**
     * Default chances for rerolling winning combinations based on balance category
     */
    private const array DEFAULT_REROLL_CHANCES = [30, 60];

    /**
     * @param GameRepositoryInterface $repository Game repository for data storage
     * @param GameFactoryInterface $factory Game factory for creating game objects
     * @param LoggerInterface $logger Logger for operations logging
     * @param bool $cheatEnabled Whether the house advantage (cheat) is enabled
     * @param array $cheatConfig Configuration for house advantage (cheat)
     */
    public function __construct(
        private GameRepositoryInterface $repository,
        private GameFactoryInterface    $factory,
        private LoggerInterface         $logger,
        private bool                    $cheatEnabled = true,
        private array                   $cheatConfig = [
            'thresholds' => self::DEFAULT_BALANCE_THRESHOLDS, 
            'chances' => self::DEFAULT_REROLL_CHANCES
        ]
    ) {
        $this->logger->info('DefaultGameService initialized', [
            'cheatEnabled' => $this->cheatEnabled,
            'cheatConfig' => $this->cheatConfig
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getGameConfig(): GameConfigDTO
    {
        $this->logger->info('Getting game configuration');
        return $this->repository->getGameConfig();
    }

    /**
     * {@inheritdoc}
     */
    public function createSession(float $initialBalance): GameSessionDTO
    {
        if ($initialBalance <= 0) {
            $this->logger->warning('Invalid initial balance', ['initialBalance' => $initialBalance]);
            throw new InvalidArgumentException('Initial balance must be greater than zero');
        }

        $this->logger->info('Creating new game session', ['initialBalance' => $initialBalance]);
        return $this->repository->createSession($initialBalance);
    }

    /**
     * {@inheritdoc}
     * @throws RandomException
     */
    public function processSpin(string $sessionId, SpinRequestDTO $request): SpinResultDTO
    {
        $this->logger->info('Processing spin request', [
            'sessionId' => $sessionId,
            'betAmount' => $request->betAmount
        ]);

        $session = $this->getActiveSession($sessionId);

        // Get game configuration
        $config = $this->repository->getGameConfig();

        // Validate bet amount
        if ($request->betAmount < $config->minBet || $request->betAmount > $config->maxBet) {
            $this->logger->warning('Invalid bet amount', [
                'betAmount' => $request->betAmount,
                'minBet' => $config->minBet,
                'maxBet' => $config->maxBet
            ]);
            throw new InvalidArgumentException(
                "Bet amount must be between {$config->minBet} and {$config->maxBet}"
            );
        }

        // Check if player has enough balance
        if ($session->balance < $request->betAmount) {
            $this->logger->warning('Insufficient funds', [
                'balance' => $session->balance,
                'betAmount' => $request->betAmount
            ]);
            throw new InvalidArgumentException('Insufficient funds');
        }

        // Generate spin result
        $result = $this->factory->generateSpinResult($request->betAmount, $config);
        
        // Apply house advantage (cheat) if enabled and the result is a win
        if ($this->cheatEnabled && $result->winAmount > 0) {
            $result = $this->applyHouseAdvantage($result, $session, $request->betAmount, $config);
        }

        // Save spin result
        $this->repository->saveSpinResult($sessionId, $result);

        // Update session balance and stats
        $session->balance -= $request->betAmount;
        $session->balance += $result->winAmount;
        $session->totalBet += $request->betAmount;
        $session->totalWin += $result->winAmount;
        $session->lastActivity = new DateTimeImmutable();
        $this->repository->updateSession($session);

        $this->logger->info('Spin processed successfully', [
            'sessionId' => $sessionId,
            'betAmount' => $request->betAmount,
            'winAmount' => $result->winAmount,
            'newBalance' => $session->balance
        ]);

        return $result;
    }

    /**
     * Apply house advantage (cheat) based on player's balance.
     * 
     * @param SpinResultDTO $result Original spin result
     * @param GameSessionDTO $session Current game session
     * @param float $betAmount Bet amount
     * @param GameConfigDTO $config Game configuration
     * @return SpinResultDTO Potentially modified spin result
     * @throws RandomException
     */
    private function applyHouseAdvantage(
        SpinResultDTO $result, 
        GameSessionDTO $session, 
        float $betAmount, 
        GameConfigDTO $config
    ): SpinResultDTO {
        // Extract thresholds and chances from config with defaults
        $thresholds = $this->cheatConfig['thresholds'] ?? self::DEFAULT_BALANCE_THRESHOLDS;
        $chances = $this->cheatConfig['chances'] ?? self::DEFAULT_REROLL_CHANCES;
        
        // Determine reroll chance based on player's balance
        $rerollChance = $this->getRerollChanceForBalance($session->balance, $thresholds, $chances);
        
        // Log the reroll chance information
        $this->logger->info('Checking house advantage', [
            'sessionId' => $session->sessionId,
            'balance' => $session->balance,
            'rerollChance' => $rerollChance
        ]);
        
        // No reroll needed if chance is 0
        if ($rerollChance <= 0) {
            return $result;
        }
        
        // Determine if we should reroll based on the chance
        if (random_int(1, 100) <= $rerollChance) {
            $this->logger->warning('Applying house advantage: rerolling winning result', [
                'sessionId' => $session->sessionId,
                'originalWinAmount' => $result->winAmount
            ]);
            
            // Generate a new result (potentially non-winning)
            $newResult = $this->factory->generateSpinResult($betAmount, $config);

            // Log the outcome of the reroll
            $this->logger->alert('House advantage applied', [
                'sessionId' => $session->sessionId,
                'originalWinAmount' => $result->winAmount,
                'newWinAmount' => $newResult->winAmount,
                'isStillWinning' => $newResult->winAmount > 0
            ]);
            
            return $newResult;
        }
        
        // No reroll occurred, return the original result
        return $result;
    }
    
    /**
     * Get the reroll chance for the given balance based on thresholds.
     * This method allows for easy extension if more balance categories are added in the future.
     * 
     * @param float $balance Current player balance
     * @param array $thresholds Balance thresholds
     * @param array $chances Corresponding reroll chances
     * @return int Reroll chance (0-100)
     */
    private function getRerollChanceForBalance(float $balance, array $thresholds, array $chances): int
    {
        // Sort thresholds in descending order to check from highest to lowest
        $sortedThresholds = $thresholds;
        arsort($sortedThresholds);

        $chance = 0; // Default: no reroll chance for lowest balance category
        // Check each threshold from highest to lowest
        foreach ($sortedThresholds as $index => $threshold) {
            if ($balance >= $threshold && isset($chances[$index])) {
                $chance = (int)$chances[$index];
                break;
            }
        }

        return $chance;
    }

    /**
     * {@inheritdoc}
     */
    public function getSession(string $sessionId): GameSessionDTO
    {
        $this->logger->info('Getting session information', ['sessionId' => $sessionId]);
        return $this->getActiveSession($sessionId);
    }

    /**
     * Get active session by ID.
     *
     * @param string $sessionId Session ID
     * @return GameSessionDTO Active session
     * @throws InvalidArgumentException If session is not found or already closed.
     */
    private function getActiveSession(string $sessionId): GameSessionDTO
    {
        $session = $this->repository->getSession($sessionId);

        if ($session === null) {
            $this->logger->warning('Session not found', ['sessionId' => $sessionId]);
            throw new InvalidArgumentException("Session with ID {$sessionId} not found");
        }

        // If session is closed, reactivate it.
        if (!$session->isActive) {
            $session->isActive = true;
            $this->repository->updateSession($session);
            $this->logger->info('Reactivated closed session', ['sessionId' => $sessionId]);
        }

        return $session;
    }

    /**
     * {@inheritdoc}
     */
    public function cashOut(string $sessionId): CashoutResultDTO
    {
        $this->logger->info('Processing cashout request', ['sessionId' => $sessionId]);

        // Get session
        $session = $this->getActiveSession($sessionId);

        // Store the current balance for the cashout result
        $cashoutAmount = $session->balance;

        // Deactivate session and reset balance to zero
        $session->isActive = false;
        $session->lastActivity = new DateTimeImmutable();
        $session->balance = 0.0; // Reset balance to zero after cashout
        
        // Update session in repository
        $this->repository->updateSession($session);

        // Create cashout result
        $result = new CashoutResultDTO(
            $session->sessionId,
            $cashoutAmount, // Use the previously stored balance amount
            $cashoutAmount - $session->totalWin + $session->totalBet,
            $session->totalBet,
            $session->totalWin
        );

        $this->logger->info('Cashout processed successfully', [
            'sessionId' => $sessionId,
            'amount' => $result->amount,
            'initialBalance' => $result->initialBalance,
            'totalBet' => $result->totalBet,
            'totalWin' => $result->totalWin,
            'netProfit' => $result->getNetProfit()
        ]);

        return $result;
    }

    public function updateSession(GameSessionDTO $updatedSession)
    {
        $this->repository->updateSession($updatedSession);
    }
}
