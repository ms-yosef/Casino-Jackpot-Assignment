<?php

declare(strict_types=1);

namespace Casino\Server\Interfaces\Factory;

use Casino\Server\DTO\GameConfigDTO;
use Casino\Server\DTO\SpinResultDTO;

/**
 * Factory interface to create game objects.
 */
interface GameFactoryInterface
{
    /**
     * Creates object of game configuration.
     */
    public function createGameConfig(): GameConfigDTO;

    /**
     * Randomly spin result generation.
     *
     * @param float $betAmount Value of the bet.
     * @param GameConfigDTO $config Game configuration.
     */
    public function generateSpinResult(float $betAmount, GameConfigDTO $config): SpinResultDTO;

    /**
     * Creates unique session ID.
     */
    public function createSessionId(): string;
}
