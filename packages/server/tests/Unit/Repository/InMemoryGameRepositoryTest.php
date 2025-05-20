<?php

declare(strict_types=1);

namespace Casino\Server\Tests\Unit\Repository;

use Casino\Server\DTO\GameConfigDTO;
use Casino\Server\DTO\GameSessionDTO;
use Casino\Server\DTO\SpinResultDTO;
use Casino\Server\Repository\InMemoryGameRepository;
use Codeception\Test\Unit;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for InMemoryGameRepository
 */
class InMemoryGameRepositoryTest extends Unit
{
    private LoggerInterface|MockObject $loggerMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loggerMock = $this->createMock(LoggerInterface::class);
    }

    /**
     * Test getting game configuration
     */
    public function testGetGameConfig(): void
    {
        // Arrange
        $repository = new InMemoryGameRepository(
            $this->loggerMock,
            3, // reels count
            1, // rows count
            1.0, // min bet
            10.0, // max bet
            10.0 // initial credits
        );

        // Act
        $config = $repository->getGameConfig();

        // Assert
        $this->assertInstanceOf(GameConfigDTO::class, $config);
        $this->assertEquals(3, $config->reelsCount);
        $this->assertEquals(1, $config->rowsCount);
        $this->assertEquals(1.0, $config->minBet);
        $this->assertEquals(10.0, $config->maxBet);
        
        // Check symbols and their payouts
        $this->assertArrayHasKey('Cherry', $config->cardsData);
        $this->assertArrayHasKey('Lemon', $config->cardsData);
        $this->assertArrayHasKey('Orange', $config->cardsData);
        $this->assertArrayHasKey('Watermelon', $config->cardsData);
        
        $this->assertEquals(10, $config->cardsData['Cherry']);
        $this->assertEquals(20, $config->cardsData['Lemon']);
        $this->assertEquals(30, $config->cardsData['Orange']);
        $this->assertEquals(40, $config->cardsData['Watermelon']);
    }

    /**
     * Test creating a new session
     */
    public function testCreateSession(): void
    {
        // Arrange
        $initialBalance = 15.0;
        $repository = new InMemoryGameRepository(
            $this->loggerMock,
            3,
            1,
            1.0,
            10.0,
            10.0
        );

        // Act
        $session = $repository->createSession($initialBalance);

        // Assert
        $this->assertInstanceOf(GameSessionDTO::class, $session);
        $this->assertNotEmpty($session->sessionId);
        $this->assertEquals($initialBalance, $session->balance);
        $this->assertEquals(0.0, $session->totalBet);
        $this->assertEquals(0.0, $session->totalWin);
        $this->assertTrue($session->isActive);
        $this->assertInstanceOf(DateTimeImmutable::class, $session->lastActivity);
        $this->assertInstanceOf(DateTimeImmutable::class, $session->createdAt);
        $this->assertEquals($session->createdAt, $session->lastActivity);
    }

    /**
     * Test creating a session with default initial balance
     */
    public function testCreateSessionWithDefaultBalance(): void
    {
        // Arrange
        $defaultInitialBalance = 10.0;
        $repository = new InMemoryGameRepository(
            $this->loggerMock,
            3,
            1,
            1.0,
            10.0,
            $defaultInitialBalance
        );

        // Act
        $session = $repository->createSession(0.0); // Should use default

        // Assert
        $this->assertEquals($defaultInitialBalance, $session->balance);
    }

    /**
     * Test getting a session
     */
    public function testGetSession(): void
    {
        // Arrange
        $repository = new InMemoryGameRepository(
            $this->loggerMock,
            3,
            1,
            1.0,
            10.0,
            10.0
        );

        // Create a session first
        $createdSession = $repository->createSession(20.0);
        $sessionId = $createdSession->sessionId;

        // Act
        $retrievedSession = $repository->getSession($sessionId);

        // Assert
        $this->assertNotNull($retrievedSession);
        $this->assertInstanceOf(GameSessionDTO::class, $retrievedSession);
        $this->assertEquals($sessionId, $retrievedSession->sessionId);
        $this->assertEquals(20.0, $retrievedSession->balance);
    }

    /**
     * Test getting a non-existent session
     */
    public function testGetNonExistentSession(): void
    {
        // Arrange
        $repository = new InMemoryGameRepository(
            $this->loggerMock,
            3,
            1,
            1.0,
            10.0,
            10.0
        );

        // Act
        $session = $repository->getSession('non_existent_session');

        // Assert
        $this->assertNull($session);
    }

    /**
     * Test updating a session
     */
    public function testUpdateSession(): void
    {
        // Arrange
        $repository = new InMemoryGameRepository(
            $this->loggerMock,
            3,
            1,
            1.0,
            10.0,
            10.0
        );

        // Create a session first
        $session = $repository->createSession(20.0);
        $sessionId = $session->sessionId;

        // Modify the session
        $session->balance = 30.0;
        $session->totalBet = 5.0;
        $session->totalWin = 15.0;
        $session->isActive = false;
        $session->lastActivity = new DateTimeImmutable();

        // Act
        $repository->updateSession($session);
        $updatedSession = $repository->getSession($sessionId);

        // Assert
        $this->assertNotNull($updatedSession);
        $this->assertEquals(30.0, $updatedSession->balance);
        $this->assertEquals(5.0, $updatedSession->totalBet);
        $this->assertEquals(15.0, $updatedSession->totalWin);
        $this->assertFalse($updatedSession->isActive);
        $this->assertNotNull($updatedSession->lastActivity);
    }

    /**
     * Test saving a spin result
     */
    public function testSaveSpinResult(): void
    {
        // Arrange
        $repository = new InMemoryGameRepository(
            $this->loggerMock,
            3,
            1,
            1.0,
            10.0,
            10.0
        );

        // Create a session first
        $initialBalance = 20.0;
        $session = $repository->createSession($initialBalance);
        $sessionId = $session->sessionId;

        // Create a spin result
        $betAmount = 2.0;
        $winAmount = 10.0;
        $spinResult = new SpinResultDTO(
            [['Cherry', 'Cherry', 'Cherry']], // reels
            $betAmount,
            $winAmount
        );

        // Act
        $repository->saveSpinResult($sessionId, $spinResult);
        $updatedSession = $repository->getSession($sessionId);

        // Assert
        $this->assertNotNull($updatedSession);
        
        // Check that balance was updated correctly: initial - bet + win
        $expectedBalance = $initialBalance - $betAmount + $winAmount;
        $this->assertEquals($expectedBalance, $updatedSession->balance);
        
        // Check that totals were updated
        $this->assertEquals($betAmount, $updatedSession->totalBet);
        $this->assertEquals($winAmount, $updatedSession->totalWin);
    }

    /**
     * Test saving multiple spin results
     */
    public function testSaveMultipleSpinResults(): void
    {
        // Arrange
        $repository = new InMemoryGameRepository(
            $this->loggerMock,
            3,
            1,
            1.0,
            10.0,
            10.0
        );

        // Create a session first
        $initialBalance = 30.0;
        $session = $repository->createSession($initialBalance);
        $sessionId = $session->sessionId;

        // Create first spin result
        $betAmount1 = 2.0;
        $winAmount1 = 10.0;
        $spinResult1 = new SpinResultDTO(
            [['Cherry', 'Cherry', 'Cherry']], // reels
            $betAmount1,
            $winAmount1
        );

        // Create second spin result
        $betAmount2 = 3.0;
        $winAmount2 = 0.0; // No win
        $spinResult2 = new SpinResultDTO(
            [['Cherry', 'Lemon', 'Orange']], // reels
            $betAmount2,
            $winAmount2
        );

        // Act
        $repository->saveSpinResult($sessionId, $spinResult1);
        $repository->saveSpinResult($sessionId, $spinResult2);
        $updatedSession = $repository->getSession($sessionId);

        // Assert
        $this->assertNotNull($updatedSession);
        
        // Check that balance was updated correctly after both spins
        $expectedBalance = $initialBalance - $betAmount1 + $winAmount1 - $betAmount2 + $winAmount2;
        $this->assertEquals($expectedBalance, $updatedSession->balance);
        
        // Check that totals were updated
        $expectedTotalBet = $betAmount1 + $betAmount2;
        $expectedTotalWin = $winAmount1 + $winAmount2;
        $this->assertEquals($expectedTotalBet, $updatedSession->totalBet);
        $this->assertEquals($expectedTotalWin, $updatedSession->totalWin);
    }

    /**
     * Test saving a spin result for a non-existent session
     */
    public function testSaveSpinResultForNonExistentSession(): void
    {
        // Arrange
        $repository = new InMemoryGameRepository(
            $this->loggerMock,
            3,
            1,
            1.0,
            10.0,
            10.0
        );

        // Create a spin result
        $betAmount = 2.0;
        $winAmount = 10.0;
        $spinResult = new SpinResultDTO(
            [['Cherry', 'Cherry', 'Cherry']], // reels
            $betAmount,
            $winAmount
        );

        // Set up logger mock to expect an error log
        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Cannot save spin result - session not found'),
                $this->arrayHasKey('sessionId')
            );

        // Act - This should not throw an exception, but log an error
        $repository->saveSpinResult('non_existent_session', $spinResult);
        
        // No assert needed as we're checking that the method doesn't throw
        // and the logger mock will fail the test if the error isn't logged
    }
}
