<?php

declare(strict_types=1);

namespace Casino\Server\DTO;

/**
 * DTO Result of the spin of a game.
 */
class SpinResultDTO
{
    /**
     * @param array<int, array<int, string>> $reels Result of the spin (position => symbol).
     * @param float $betAmount Amount of the bet.
     * @param float $winAmount Win amount.
     * @param array<int, array<int, int>> $winningLines Winning lines. (line number => [positions])
     * @param array<string, mixed> $features Active special features (name => parameters).
     * @param \DateTimeImmutable $timestamp Time of the spin.
     */
    public function __construct(
        public readonly array $reels,
        public readonly float $betAmount,
        public readonly float $winAmount,
        public readonly array $winningLines = [],
        public readonly array $features = [],
        public readonly \DateTimeImmutable $timestamp = new \DateTimeImmutable()
    ) {
    }

    /**
     * Checks if the spin is a win.
     */
    public function isWin(): bool
    {
        return $this->winAmount > 0;
    }

    /**
     * Returns multiplier concerning the bet.
     */
    public function getMultiplier(): float
    {
        if ($this->betAmount <= 0) {
            return 0;
        }

        return $this->winAmount / $this->betAmount;
    }
}
