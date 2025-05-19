<?php

declare(strict_types=1);

namespace Casino\Server\DTO;


/**
 * DTO for spin request
 */
class SpinRequestDTO
{
    /**
     * @param float $betAmount Amount of the bet.
     * @param int|null $linesCount Number of active lines (null - all lines).
     */
    public function __construct(
        public readonly float $betAmount,
        public readonly ?int $linesCount = null
    ) {
    }
}