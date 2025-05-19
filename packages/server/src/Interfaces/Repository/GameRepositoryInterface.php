<?php

declare(strict_types=1);

namespace Casino\Server\Interfaces\Repository;

use Casino\Server\DTO\GameConfigDTO;
use Casino\Server\DTO\SpinResultDTO;
use Casino\Server\DTO\GameSessionDTO;

/**
 * Repository interface for game data working.
 */
interface GameRepositoryInterface
{
    /**
     * Gets configuration of a game.
     */
    public function getGameConfig(): GameConfigDTO;

    /**
     * Creates new gaming session.
     *
     * @param float $initialBalance Initial player balance
     */
    public function createSession(float $initialBalance): GameSessionDTO;

    /**
     * Gets game session by ID.
     *
     * @param string $sessionId session ID
     */
    public function getSession(string $sessionId): ?GameSessionDTO;

    /**
     * Renew game session.
     *
     * @param GameSessionDTO $session Renewed session.
     */
    public function updateSession(GameSessionDTO $session): void;

    /**
     * Saves result of a spin.
     *
     * @param string $sessionId session ID
     * @param SpinResultDTO $result Spin result.
     */
    public function saveSpinResult(string $sessionId, SpinResultDTO $result): void;
}
