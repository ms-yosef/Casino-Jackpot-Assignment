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

/**
 * Default implementation of the game service.
 *
 * This service is responsible for handling game operations and business logic.
 */
readonly class DefaultGameService implements GameServiceInterface
{
    /**
     * @param GameRepositoryInterface $repository Game repository for data storage
     * @param GameFactoryInterface $factory Game factory for creating game objects
     * @param LoggerInterface $logger Logger for operations logging
     */
    public function __construct(
        private GameRepositoryInterface $repository,
        private GameFactoryInterface    $factory,
        private LoggerInterface         $logger
    ) {
        $this->logger->info('DefaultGameService initialized');
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

        // Save spin result
        $this->repository->saveSpinResult($sessionId, $result);

        $this->logger->info('Spin processed successfully', [
            'sessionId' => $sessionId,
            'betAmount' => $request->betAmount,
            'winAmount' => $result->winAmount,
            'newBalance' => $session->balance - $request->betAmount + $result->winAmount
        ]);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function cashOut(string $sessionId): CashoutResultDTO
    {
        $this->logger->info('Processing cashout request', ['sessionId' => $sessionId]);

        // Get session
        $session = $this->getActiveSession($sessionId);

        // Deactivate session
        $session->isActive = false;
        $session->lastActivity = new DateTimeImmutable();
        
        // Refresh session in storage.
        $this->repository->updateSession($session);

        // Create cashout result
        $result = new CashoutResultDTO(
            $session->sessionId,
            $session->balance,
            $session->balance - $session->totalWin + $session->totalBet,
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

    /**
     * Get active session by its ID, or throw an exception.
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

        // Check if session is closed
        if (!$session->isActive) {
            $this->logger->warning('Session is already closed', ['sessionId' => $sessionId]);
            throw new InvalidArgumentException("Session with ID {$sessionId} is already closed");
        }

        return $session;
    }
}
