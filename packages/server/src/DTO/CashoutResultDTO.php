<?php

declare(strict_types=1);

namespace Casino\Server\DTO;

/**
 * DTO Result of cashout
 */
class CashoutResultDTO
{
    /**
     * @param string $sessionId Session ID
     * @param float $amount Cashout amount (sum).
     * @param float $initialBalance Initial balance.
     * @param float $totalBet Total amount of bets.
     * @param float $totalWin Total amount of wins.
     * @param \DateTimeImmutable $timestamp Time of the cashout.
     */
    public function __construct(
        public readonly string $sessionId,
        public readonly float $amount,
        public readonly float $initialBalance,
        public readonly float $totalBet,
        public readonly float $totalWin,
        public readonly \DateTimeImmutable $timestamp = new \DateTimeImmutable()
    ) {
    }

    /**
     * Returns net profit/loss of the player.
     */
    public function getNetProfit(): float
    {
        return $this->amount - $this->initialBalance;
    }

    /**
     * Checks if the player made a profit.
     */
    public function isProfit(): bool
    {
        return $this->getNetProfit() > 0;
    }
}
