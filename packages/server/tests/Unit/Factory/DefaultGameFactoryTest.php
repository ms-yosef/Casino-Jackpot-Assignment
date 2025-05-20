<?php

declare(strict_types=1);

namespace Casino\Server\Tests\Unit\Factory;

use Casino\Server\DTO\GameConfigDTO;
use Casino\Server\DTO\SpinResultDTO;
use Casino\Server\Factory\DefaultGameFactory;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for DefaultGameFactory
 */
class DefaultGameFactoryTest extends Unit
{
    private LoggerInterface|MockObject $loggerMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loggerMock = $this->createMock(LoggerInterface::class);
    }

    /**
     * Test creating game configuration
     */
    public function testCreateGameConfig(): void
    {
        // Arrange
        $reelsCount = 3;
        $rowsCount = 1;
        $minBet = 1.0;
        $maxBet = 10.0;
        $cardsData = [
            'Cherry' => 10,
            'Lemon' => 20,
            'Orange' => 30,
            'Watermelon' => 40
        ];

        $factory = new DefaultGameFactory(
            $this->loggerMock,
            $reelsCount,
            $rowsCount,
            $minBet,
            $maxBet,
            $cardsData
        );

        // Act
        $config = $factory->createGameConfig();

        // Assert
        $this->assertInstanceOf(GameConfigDTO::class, $config);
        $this->assertEquals($reelsCount, $config->reelsCount);
        $this->assertEquals($rowsCount, $config->rowsCount);
        $this->assertEquals($minBet, $config->minBet);
        $this->assertEquals($maxBet, $config->maxBet);
        $this->assertEquals($cardsData, $config->cardsData);
    }

    /**
     * Test generating spin result
     */
    public function testGenerateSpinResult(): void
    {
        // Arrange
        $reelsCount = 3;
        $rowsCount = 1;
        $minBet = 1.0;
        $maxBet = 10.0;
        $cardsData = [
            'Cherry' => 10,
            'Lemon' => 20,
            'Orange' => 30,
            'Watermelon' => 40
        ];

        $factory = new DefaultGameFactory(
            $this->loggerMock,
            $reelsCount,
            $rowsCount,
            $minBet,
            $maxBet,
            $cardsData
        );

        $betAmount = 5.0;
        $config = new GameConfigDTO(
            $cardsData,
            $reelsCount,
            $rowsCount,
            $minBet,
            $maxBet,
            []
        );

        // Act
        $result = $factory->generateSpinResult($betAmount, $config);

        // Assert
        $this->assertInstanceOf(SpinResultDTO::class, $result);
        $this->assertEquals($betAmount, $result->betAmount);

        // Check that the result has the correct structure
        $this->assertIsArray($result->reels);
        $this->assertCount($rowsCount, $result->reels);

        foreach ($result->reels as $row) {
            $this->assertIsArray($row);
            $this->assertCount($reelsCount, $row);

            foreach ($row as $symbol) {
                $this->assertContains($symbol, array_keys($cardsData));
            }
        }
    }

    /**
     * Test that win amount is calculated correctly for winning combinations
     */
    public function testWinAmountCalculation(): void
    {
        // Arrange
        $reelsCount = 3;
        $rowsCount = 1;
        $minBet = 1.0;
        $maxBet = 10.0;
        $cardsData = [
            'Cherry' => 10,
            'Lemon' => 20,
            'Orange' => 30,
            'Watermelon' => 40
        ];

        // Create a regular factory instance
        $factory = new DefaultGameFactory(
            $this->loggerMock,
            $reelsCount,
            $rowsCount,
            $minBet,
            $maxBet,
            $cardsData
        );

        $betAmount = 2.0;
        $config = new GameConfigDTO(
            $cardsData,
            $reelsCount,
            $rowsCount,
            $minBet,
            $maxBet,
            []
        );

        // Use Reflection to access the private method calculateWin
        $reflectionMethod = new \ReflectionMethod(DefaultGameFactory::class, 'calculateWin');
        $reflectionMethod->setAccessible(true);

        // Create a matrix with all 'Cherry' symbols for a guaranteed win
        $matrix = [['Cherry', 'Cherry', 'Cherry']];
        
        // Call the private method directly
        $winData = $reflectionMethod->invoke($factory, $matrix, $betAmount, $config);
        
        // Assert
        $expectedWinAmount = $betAmount * $cardsData['Cherry'];
        $this->assertEquals($expectedWinAmount, $winData['totalWin']);
        
        // Check that we have a winning line
        $this->assertCount(1, $winData['winLines']);
        $this->assertEquals('row', $winData['winLines'][0]['type']);
        $this->assertEquals('Cherry', $winData['winLines'][0]['combination']);
        $this->assertEquals($expectedWinAmount, $winData['winLines'][0]['win']);
    }

    /**
     * Test generating spin result with different bet amounts
     */
    public function testGenerateSpinResultWithDifferentBetAmounts(): void
    {
        // Arrange
        $reelsCount = 3;
        $rowsCount = 1;
        $minBet = 1.0;
        $maxBet = 10.0;
        $cardsData = [
            'Cherry' => 10,
            'Lemon' => 20,
            'Orange' => 30,
            'Watermelon' => 40
        ];

        $factory = new DefaultGameFactory(
            $this->loggerMock,
            $reelsCount,
            $rowsCount,
            $minBet,
            $maxBet,
            $cardsData
        );

        $config = new GameConfigDTO(
            $cardsData,
            $reelsCount,
            $rowsCount,
            $minBet,
            $maxBet,
            []
        );

        // Test with minimum bet
        $minBetResult = $factory->generateSpinResult($minBet, $config);
        $this->assertEquals($minBet, $minBetResult->betAmount);

        // Test with maximum bet
        $maxBetResult = $factory->generateSpinResult($maxBet, $config);
        $this->assertEquals($maxBet, $maxBetResult->betAmount);

        // Test with a bet in between
        $midBet = ($minBet + $maxBet) / 2;
        $midBetResult = $factory->generateSpinResult($midBet, $config);
        $this->assertEquals($midBet, $midBetResult->betAmount);
    }

    /**
     * Test that different spins generate different results
     */
    public function testDifferentSpinsGenerateDifferentResults(): void
    {
        // Arrange
        $reelsCount = 3;
        $rowsCount = 1;
        $minBet = 1.0;
        $maxBet = 10.0;
        $cardsData = [
            'Cherry' => 10,
            'Lemon' => 20,
            'Orange' => 30,
            'Watermelon' => 40
        ];

        $factory = new DefaultGameFactory(
            $this->loggerMock,
            $reelsCount,
            $rowsCount,
            $minBet,
            $maxBet,
            $cardsData
        );

        $betAmount = 5.0;
        $config = new GameConfigDTO(
            $cardsData,
            $reelsCount,
            $rowsCount,
            $minBet,
            $maxBet,
            []
        );

        // Generate multiple spin results
        $results = [];
        $numSpins = 10;
        for ($i = 0; $i < $numSpins; $i++) {
            $results[] = $factory->generateSpinResult($betAmount, $config);
        }

        // Count unique combinations
        $uniqueCombinations = [];
        foreach ($results as $result) {
            $combination = json_encode($result->reels);
            $uniqueCombinations[$combination] = true;
        }

        // Assert that we have at least some different combinations
        // Note: This is a probabilistic test, so it could theoretically fail
        // even with a correct implementation, but it's very unlikely
        $this->assertGreaterThan(1, count($uniqueCombinations),
            "Expected multiple different spin results, but all were identical");
    }
}
