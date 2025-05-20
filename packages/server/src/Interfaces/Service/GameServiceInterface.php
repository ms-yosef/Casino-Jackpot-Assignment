<?php

declare(strict_types=1);

namespace Casino\Server\Interfaces\Service;

use Casino\Server\DTO\GameConfigDTO;
use Casino\Server\DTO\GameSessionDTO;
use Casino\Server\DTO\SpinRequestDTO;
use Casino\Server\DTO\SpinResultDTO;
use Casino\Server\DTO\CashoutResultDTO;

/**
 * Service Interface of a gaming logic.
 */
interface GameServiceInterface
{
    /**
     * Gets game configuration.
     */
    public function getGameConfig(): GameConfigDTO;

    /**
     * Creates new game session.
     *
     * @param float $initialBalance Initial player balance
     */
    public function createSession(float $initialBalance): GameSessionDTO;

    /**
     * Processes spin request.
     *
     * @param string $sessionId session ID
     * @param SpinRequestDTO $request spin request params.
     * @throws \InvalidArgumentException If the session not found or insufficient funds.
     */
    public function processSpin(string $sessionId, SpinRequestDTO $request): SpinResultDTO;

    /**
     * Performs cash out and close the session.
     *
     * @param string $sessionId session ID
     * @throws \InvalidArgumentException If the session not found
     */
    public function cashOut(string $sessionId): CashoutResultDTO;

    /**
     * Gets session information by ID.
     *
     * @param string $sessionId session ID
     * @throws \InvalidArgumentException If the session not found
     */
    public function getSession(string $sessionId): GameSessionDTO;
}
