<?php

declare(strict_types=1);

namespace Casino\Server\Repositories;

use Casino\Server\DTO\GameConfigDTO;
use Casino\Server\Interfaces\Repository\GameRepositoryInterface;

/**
 * Abstract base class for game repositories.
 * Contains common functionality for all repository implementations.
 */
abstract class AbstractGameRepository implements GameRepositoryInterface
{
    /**
     * @var GameConfigDTO Game configuration
     */
    protected GameConfigDTO $gameConfig;

    /**
     * Creates a game configuration based on environment settings or defaults
     *
     * @param int $reelsCount Number of reels in the game
     * @param int $rowsCount Number of rows in the game
     * @param float $minBet Minimum allowed bet amount
     * @param float $maxBet Maximum allowed bet amount
     * @return GameConfigDTO The game configuration
     */
    protected function createGameConfig(
        int $reelsCount,
        int $rowsCount,
        float $minBet,
        float $maxBet
    ): GameConfigDTO {
        // Parse symbols settings from environment or use defaults
        $symbolsSettings = [];
        $envSymbols = json_decode(getenv('GAME_SYMBOLS_SETTINGS') ?: '{}', true);

        if (!empty($envSymbols) && isset($envSymbols['chars']) && isset($envSymbols['values'])) {
            $chars = $envSymbols['chars'];
            $values = $envSymbols['values'];

            for ($i = 0, $iMax = count($chars); $i < $iMax; $i++) {
                if (isset($values[$i])) {
                    $symbolsSettings[$chars[$i]] = $values[$i];
                }
            }
        }

        // Use default symbols if none were provided or parsing failed
        if (empty($symbolsSettings)) {
            $symbolsSettings = [
                'C' => 10, // Cherry
                'L' => 20, // Lemon
                'O' => 30, // Orange
                'W' => 40  // Watermelon
            ];
        }

        // Initialize game configuration with proper signature
        return new GameConfigDTO(
            $symbolsSettings,
            $reelsCount,
            $rowsCount,
            $minBet,
            $maxBet,
            []
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getGameConfig(): GameConfigDTO
    {
        return $this->gameConfig;
    }
}
