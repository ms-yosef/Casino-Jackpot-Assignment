<?php

declare(strict_types=1);

namespace Casino\Server\DTO;

/**
 * DTO Game session
 */
class GameSessionDTO
{
    /**
     * @param string $sessionId Session unique ID
     * @param float $balance Current player balance.
     * @param float $totalBet Total amount of bets in the session.
     * @param float $totalWin Total amount of wins in the session.
     * @param \DateTimeImmutable $createdAt Session creation time.
     * @param \DateTimeImmutable|null $lastActivity Time of the last activity.
     * @param bool $isActive Is the session active?
     */
    public function __construct(
        public readonly string $sessionId,
        public float $balance,
        public float $totalBet = 0.0,
        public float $totalWin = 0.0,
        public readonly \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        public ?\DateTimeImmutable $lastActivity = null,
        public bool $isActive = true
    ) {
        if ($this->lastActivity === null) {
            $this->lastActivity = $this->createdAt;
        }
    }
}