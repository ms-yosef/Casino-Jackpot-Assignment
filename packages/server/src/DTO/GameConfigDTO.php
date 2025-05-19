<?php

declare(strict_types=1);

namespace Casino\Server\DTO;

/**
 * DTO Game config
 */
class GameConfigDTO
{
    /**
     * @param array<string, float> $cardsData Table of symbols and their payouts (symbol => coefficient).
     * @param int $reelsCount Number of reels.
     * @param int $rowsCount Number of rows.
     * @param float $minBet Min bet.
     * @param float $maxBet Max bet.
     * @param array<string, array<int, int>> $specialSymbolsPositions Positions of special symbols.
     */
    public function __construct(
        public readonly array $cardsData,
        public readonly int $reelsCount,
        public readonly int $rowsCount,
        public readonly float $minBet,
        public readonly float $maxBet,
        public readonly array $specialSymbolsPositions = []
    ) {
    }
}