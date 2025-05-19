<?php

declare(strict_types=1);

namespace Casino\Server\Factory;

use Casino\Server\DTO\GameConfigDTO;
use Casino\Server\DTO\SpinResultDTO;
use Casino\Server\Interfaces\Factory\GameFactoryInterface;
use Psr\Log\LoggerInterface;
use Random\RandomException;

/**
 * Default implementation of the game factory.
 *
 * This factory is responsible for creating game objects and generating spin results.
 */
readonly class DefaultGameFactory implements GameFactoryInterface
{
    /**
     * @param LoggerInterface $logger Logger for operations logging
     * @param int $reelsCount Number of reels in the game
     * @param int $rowsCount Number of rows in the game
     * @param float $minBet Minimum allowed bet amount
     * @param float $maxBet Maximum allowed bet amount
     * @param array|null $symbolsSettings Settings for game symbols from environment
     * @param float $initialCredits Initial credits for new sessions
     */
    public function __construct(
        private LoggerInterface $logger,
        private int             $reelsCount,
        private int             $rowsCount,
        private float           $minBet,
        private float           $maxBet,
        private ?array          $symbolsSettings = null,
        private float           $initialCredits = 10.0
    ) {
        $this->logger->info('DefaultGameFactory initialized with configuration', [
            'reelsCount' => $this->reelsCount,
            'rowsCount' => $this->rowsCount,
            'minBet' => $this->minBet,
            'maxBet' => $this->maxBet,
            'initialCredits' => $this->initialCredits,
            'hasSymbolsSettings' => $this->symbolsSettings !== null
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function createGameConfig(): GameConfigDTO
    {
        $this->logger->info('Creating game configuration');

        // Generate cards data from symbols settings or use defaults
        $cardsData = $this->getCardsData();

        return new GameConfigDTO(
            $cardsData,
            $this->reelsCount,
            $this->rowsCount,
            $this->minBet,
            $this->maxBet,
            []
        );
    }

    /**
     * Get cards data from symbols settings or use defaults.
     *
     * @return array<string, float> Table of symbols and their payouts
     */
    private function getCardsData(): array
    {
        $cardsData = [];
        // If we have symbol settings from environment, use them
        if ($this->symbolsSettings !== null 
            && isset($this->symbolsSettings['names']) 
            && isset($this->symbolsSettings['values'])
            && is_array($this->symbolsSettings['names'])
            && is_array($this->symbolsSettings['values'])
        ) {
            $names = $this->symbolsSettings['names'];
            $values = $this->symbolsSettings['values'];
            
            // Create mapping of symbol names to values
            foreach ($names as $index => $name) {
                if (isset($values[$index])) {
                    $cardsData[$name] = (float)$values[$index];
                }
            }
            
            if (!empty($cardsData)) {
                $this->logger->info('Using custom symbols configuration', [
                    'symbols' => array_keys($cardsData)
                ]);
            }
        }
        if ($cardsData === []) {
            // Fallback to default values
            $this->logger->info('Using default symbols configuration');
            $cardsData = [
                'Cherry' => 10,
                'Lemon' => 20,
                'Orange' => 30,
                'Watermelon' => 40
            ];
        }
        
        return $cardsData;
    }

    /**
     * {@inheritdoc}
     * @throws RandomException
     */
    public function generateSpinResult(float $betAmount, GameConfigDTO $config): SpinResultDTO
    {
        $this->logger->info('Generating spin result', ['betAmount' => $betAmount]);
        
        // Generate random matrix of symbols
        $matrix = $this->generateRandomMatrix($config);
        
        // Calculate win amount based on the matrix and bet amount
        $winData = $this->calculateWin($matrix, $betAmount, $config);
        $winAmount = $winData['totalWin'];
        $winLines = $winData['winLines'];
        
        // Create spin result
        $result = new SpinResultDTO(
            $matrix,
            $betAmount,
            $winAmount,
            $winLines,
            []
        );
        
        $this->logger->info('Spin result generated', [
            'betAmount' => $betAmount,
            'winAmount' => $winAmount,
            'winLines' => count($winLines)
        ]);
        
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function createSessionId(): string
    {
        $sessionId = uniqid('session_', true);
        $this->logger->info('Created new session ID', ['sessionId' => $sessionId]);

        return $sessionId;
    }

    /**
     * Generate a random matrix of symbols based on the game configuration.
     *
     * @param GameConfigDTO $config Game configuration
     * @return array 2D array representing the game matrix
     * @throws RandomException
     */
    private function generateRandomMatrix(GameConfigDTO $config): array
    {
        $matrix = [];
        $symbols = array_keys($config->cardsData);
        
        for ($row = 0; $row < $config->rowsCount; $row++) {
            $rowSymbols = [];
            for ($reel = 0; $reel < $config->reelsCount; $reel++) {
                // Randomly select a symbol
                $randomIndex = random_int(0, count($symbols) - 1);
                $rowSymbols[] = $symbols[$randomIndex];
            }
            $matrix[] = $rowSymbols;
        }
        
        return $matrix;
    }

    /**
     * Calculate win amount based on the matrix, bet amount, and game configuration.
     *
     * @param array $matrix 2D array representing the game matrix
     * @param float $betAmount Bet amount
     * @param GameConfigDTO $config Game configuration
     * @return array Array with total win amount and win lines
     */
    private function calculateWin(array $matrix, float $betAmount, GameConfigDTO $config): array
    {
        $totalWin = 0;
        $winLines = [];
        
        // Check for winning combinations in rows
        foreach ($matrix as $row => $rowValue) {
            $rowSymbols = $rowValue;
            $winData = $this->checkLineWin($rowSymbols, $betAmount, $config);
            
            if ($winData['win'] > 0) {
                $totalWin += $winData['win'];
                $winLines[] = [
                    'type' => 'row',
                    'index' => $row,
                    'symbols' => $rowSymbols,
                    'win' => $winData['win'],
                    'combination' => $winData['combination']
                ];
            }
        }

        // Check for scatter wins
        $scatterWin = $this->checkScatterWin($matrix, $betAmount, $config);
        if ($scatterWin['win'] > 0) {
            $totalWin += $scatterWin['win'];
            $winLines[] = [
                'type' => 'scatter',
                'count' => $scatterWin['count'],
                'win' => $scatterWin['win']
            ];
        }
        
        return [
            'totalWin' => $totalWin,
            'winLines' => $winLines
        ];
    }

    /**
     * Check for winning combinations in a line.
     *
     * @param array $line Array of symbols in a line
     * @param float $betAmount Bet amount
     * @param GameConfigDTO $config Game configuration
     * @return array Win data with win amount and combination type
     */
    private function checkLineWin(array $line, float $betAmount, GameConfigDTO $config): array
    {
        // Win if all symbols in the line are the same.
        if (count(array_unique($line)) === 1) {
            $symbol = $line[0];
            $winAmount = $config->cardsData[$symbol] * $betAmount;
            
            return [
                'win' => $winAmount,
                'combination' => $symbol
            ];
        }
        
        // No win
        return [
            'win' => 0,
            'combination' => ''
        ];
    }

    /**
     * Check for scatter wins in the entire matrix.
     * Future functionality for more realistic game.
     * But in the current configuration scatter symbols don't use.
     *
     * @param array $matrix 2D array representing the game matrix
     * @param float $betAmount Bet amount
     * @param GameConfigDTO $config Game configuration
     * @return array Win data with win amount and scatter count
     */
    private function checkScatterWin(array $matrix, float $betAmount, GameConfigDTO $config): array
    {
        // No scatter wins in the current configuration.
        return [
            'win' => 0,
            'count' => 0
        ];
    }
}
