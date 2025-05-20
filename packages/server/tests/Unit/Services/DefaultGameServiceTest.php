<?php

declare(strict_types=1);

namespace Casino\Server\Tests\Unit\Services;

use Casino\Server\DTO\CashoutResultDTO;
use Casino\Server\DTO\GameConfigDTO;
use Casino\Server\DTO\GameSessionDTO;
use Casino\Server\DTO\SpinRequestDTO;
use Casino\Server\DTO\SpinResultDTO;
use Casino\Server\Interfaces\Factory\GameFactoryInterface;
use Casino\Server\Interfaces\Repository\GameRepositoryInterface;
use Casino\Server\Services\DefaultGameService;
use Codeception\Test\Unit;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for DefaultGameService
 */
class DefaultGameServiceTest extends Unit
{
    private LoggerInterface|MockObject $loggerMock;
    private GameRepositoryInterface|MockObject $repositoryMock;
    private GameFactoryInterface|MockObject $factoryMock;
    private GameConfigDTO $gameConfig;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->repositoryMock = $this->createMock(GameRepositoryInterface::class);
        $this->factoryMock = $this->createMock(GameFactoryInterface::class);

        // Create game config
        $this->gameConfig = new GameConfigDTO(
            [
                'Cherry' => 10,
                'Lemon' => 20,
                'Orange' => 30,
                'Watermelon' => 40
            ],
            3, // reels count
            1, // rows count
            1.0, // min bet
            10.0, // max bet
            []
        );

        // Setup repository mock to return game config
        $this->repositoryMock->method('getGameConfig')
            ->willReturn($this->gameConfig);
    }

    /**
     * Test creating a new session
     */
    public function testCreateSession(): void
    {
        // Arrange
        $initialBalance = 10.0;
        $sessionId = 'test_session_123';
        $session = new GameSessionDTO(
            $sessionId,
            $initialBalance,
            0.0,
            0.0,
            new DateTimeImmutable(),
            null,
            true
        );

        $this->repositoryMock->expects($this->once())
            ->method('createSession')
            ->with($initialBalance)
            ->willReturn($session);

        $service = new DefaultGameService(
            $this->repositoryMock,
            $this->factoryMock,
            $this->loggerMock
        );

        // Act
        $result = $service->createSession($initialBalance);

        // Assert
        $this->assertSame($session, $result);
        $this->assertEquals($sessionId, $result->sessionId);
        $this->assertEquals($initialBalance, $result->balance);
    }

    /**
     * Test that creating a session with negative balance throws exception
     */
    public function testCreateSessionWithNegativeBalanceThrowsException(): void
    {
        // Arrange
        $service = new DefaultGameService(
            $this->repositoryMock,
            $this->factoryMock,
            $this->loggerMock
        );

        // Assert & Act
        $this->expectException(InvalidArgumentException::class);
        $service->createSession(-10.0);
    }

    /**
     * Test processing a spin with no cheat (low balance)
     */
    public function testProcessSpinWithNoCheat(): void
    {
        // Arrange
        $sessionId = 'test_session_123';
        $betAmount = 1.0;
        $winAmount = 10.0;
        $initialBalance = 30.0; // Low balance, no cheat

        // Create session
        $session = new GameSessionDTO(
            $sessionId,
            $initialBalance,
            0.0,
            0.0,
            new DateTimeImmutable(),
            null,
            true
        );

        // Create spin request
        $request = new SpinRequestDTO($betAmount);

        // Create spin result
        $spinResult = new SpinResultDTO(
            [['Cherry', 'Cherry', 'Cherry']], // reels
            $betAmount,
            $winAmount
        );

        // Setup repository mock
        $this->repositoryMock->expects($this->once())
            ->method('getSession')
            ->with($sessionId)
            ->willReturn($session);

        // Setup factory mock
        $this->factoryMock->expects($this->once())
            ->method('generateSpinResult')
            ->with($betAmount, $this->gameConfig)
            ->willReturn($spinResult);

        // Service with cheat enabled
        $service = new DefaultGameService(
            $this->repositoryMock,
            $this->factoryMock,
            $this->loggerMock,
            true // cheat enabled
        );

        // Act
        $result = $service->processSpin($sessionId, $request);

        // Assert
        $this->assertSame($spinResult, $result);
        $this->assertEquals($betAmount, $result->betAmount);
        $this->assertEquals($winAmount, $result->winAmount);
    }

    /**
     * Test processing a spin with cheat (medium balance)
     */
    public function testProcessSpinWithMediumBalanceCheat(): void
    {
        // Arrange
        $sessionId = 'test_session_123';
        $betAmount = 1.0;
        $winAmount = 10.0;
        $initialBalance = 50.0; // Medium balance, 30% chance to cheat

        // Create session
        $session = new GameSessionDTO(
            $sessionId,
            $initialBalance,
            0.0,
            0.0,
            new DateTimeImmutable(),
            null,
            true
        );

        // Create spin request
        $request = new SpinRequestDTO($betAmount);

        // Create original winning spin result
        $originalSpinResult = new SpinResultDTO(
            [['Cherry', 'Cherry', 'Cherry']], // reels
            $betAmount,
            $winAmount
        );

        // Create new spin result (after cheat)
        $newSpinResult = new SpinResultDTO(
            [['Cherry', 'Lemon', 'Orange']], // reels
            $betAmount,
            0.0 // No win after cheat
        );

        // Setup repository mock
        $this->repositoryMock->expects($this->once())
            ->method('getSession')
            ->with($sessionId)
            ->willReturn($session);

        // Setup factory mock to return winning result first, then losing result
        $this->factoryMock->expects($this->exactly(2))
            ->method('generateSpinResult')
            ->with($betAmount, $this->gameConfig)
            ->willReturnOnConsecutiveCalls($originalSpinResult, $newSpinResult);

        // Service with cheat enabled and 100% chance to reroll for testing
        $service = new DefaultGameService(
            $this->repositoryMock,
            $this->factoryMock,
            $this->loggerMock,
            true, // cheat enabled
            [
                'thresholds' => [40, 60],
                'chances' => [100, 100] // 100% chance to reroll for testing
            ]
        );

        // Act
        $result = $service->processSpin($sessionId, $request);

        // Assert
        $this->assertSame($newSpinResult, $result);
        $this->assertEquals($betAmount, $result->betAmount);
        $this->assertEquals(0.0, $result->winAmount); // Should be 0 after cheat
    }

    /**
     * Test processing a spin with cheat (high balance)
     */
    public function testProcessSpinWithHighBalanceCheat(): void
    {
        // Arrange
        $sessionId = 'test_session_123';
        $betAmount = 1.0;
        $winAmount = 10.0;
        $initialBalance = 70.0; // High balance, 60% chance to cheat

        // Create session
        $session = new GameSessionDTO(
            $sessionId,
            $initialBalance,
            0.0,
            0.0,
            new DateTimeImmutable(),
            null,
            true
        );

        // Create spin request
        $request = new SpinRequestDTO($betAmount);

        // Create original winning spin result
        $originalSpinResult = new SpinResultDTO(
            [['Cherry', 'Cherry', 'Cherry']], // reels
            $betAmount,
            $winAmount
        );

        // Create new spin result (after cheat)
        $newSpinResult = new SpinResultDTO(
            [['Cherry', 'Lemon', 'Orange']], // reels
            $betAmount,
            0.0 // No win after cheat
        );

        // Setup repository mock
        $this->repositoryMock->expects($this->once())
            ->method('getSession')
            ->with($sessionId)
            ->willReturn($session);

        // Setup factory mock to return winning result first, then losing result
        $this->factoryMock->expects($this->exactly(2))
            ->method('generateSpinResult')
            ->with($betAmount, $this->gameConfig)
            ->willReturnOnConsecutiveCalls($originalSpinResult, $newSpinResult);

        // Service with cheat enabled and 100% chance to reroll for testing
        $service = new DefaultGameService(
            $this->repositoryMock,
            $this->factoryMock,
            $this->loggerMock,
            true, // cheat enabled
            [
                'thresholds' => [40, 60],
                'chances' => [100, 100] // 100% chance to reroll for testing
            ]
        );

        // Act
        $result = $service->processSpin($sessionId, $request);

        // Assert
        $this->assertSame($newSpinResult, $result);
        $this->assertEquals($betAmount, $result->betAmount);
        $this->assertEquals(0.0, $result->winAmount); // Should be 0 after cheat
    }

    /**
     * Test processing a spin with invalid bet amount
     */
    public function testProcessSpinWithInvalidBetAmount(): void
    {
        // Arrange
        $sessionId = 'test_session_123';
        $initialBalance = 10.0;

        // Create session
        $session = new GameSessionDTO(
            $sessionId,
            $initialBalance,
            0.0,
            0.0,
            new DateTimeImmutable(),
            null,
            true
        );

        // Setup repository mock
        $this->repositoryMock->expects($this->once())
            ->method('getSession')
            ->with($sessionId)
            ->willReturn($session);

        $service = new DefaultGameService(
            $this->repositoryMock,
            $this->factoryMock,
            $this->loggerMock
        );

        // Assert & Act - Bet too low
        $this->expectException(InvalidArgumentException::class);
        $service->processSpin($sessionId, new SpinRequestDTO(0.5)); // Min bet is 1.0
    }

    /**
     * Test processing a spin with insufficient funds
     */
    public function testProcessSpinWithInsufficientFunds(): void
    {
        // Arrange
        $sessionId = 'test_session_123';
        $betAmount = 5.0;
        $initialBalance = 3.0; // Less than bet amount

        // Create session
        $session = new GameSessionDTO(
            $sessionId,
            $initialBalance,
            0.0,
            0.0,
            new DateTimeImmutable(),
            null,
            true
        );

        // Setup repository mock
        $this->repositoryMock->expects($this->once())
            ->method('getSession')
            ->with($sessionId)
            ->willReturn($session);

        $service = new DefaultGameService(
            $this->repositoryMock,
            $this->factoryMock,
            $this->loggerMock
        );

        // Assert & Act
        $this->expectException(InvalidArgumentException::class);
        $service->processSpin($sessionId, new SpinRequestDTO($betAmount));
    }

    /**
     * Test cash out
     */
    public function testCashOut(): void
    {
        // Arrange
        $sessionId = 'test_session_123';
        $balance = 50.0;
        $totalBet = 20.0;
        $totalWin = 60.0;
        $initialBalance = 10.0; // Initial balance was 10, then won 60 and bet 20

        // Create session
        $session = new GameSessionDTO(
            $sessionId,
            $balance,
            $totalBet,
            $totalWin,
            new DateTimeImmutable(),
            null,
            true
        );

        // Setup repository mock
        $this->repositoryMock->expects($this->once())
            ->method('getSession')
            ->with($sessionId)
            ->willReturn($session);

        $this->repositoryMock->expects($this->once())
            ->method('updateSession')
            ->with($this->callback(function (GameSessionDTO $updatedSession) use ($sessionId) {
                return $updatedSession->sessionId === $sessionId && !$updatedSession->isActive;
            }));

        $service = new DefaultGameService(
            $this->repositoryMock,
            $this->factoryMock,
            $this->loggerMock
        );

        // Act
        $result = $service->cashOut($sessionId);

        // Assert
        $this->assertInstanceOf(CashoutResultDTO::class, $result);
        $this->assertEquals($sessionId, $result->sessionId);
        $this->assertEquals($balance, $result->amount);
        $this->assertEquals($initialBalance, $result->initialBalance);
        $this->assertEquals($totalBet, $result->totalBet);
        $this->assertEquals($totalWin, $result->totalWin);
        $this->assertEquals($balance - $initialBalance, $result->getNetProfit());
    }

    /**
     * Test cash out with non-existent session
     */
    public function testCashOutWithNonExistentSession(): void
    {
        // Arrange
        $sessionId = 'non_existent_session';

        // Setup repository mock
        $this->repositoryMock->expects($this->once())
            ->method('getSession')
            ->with($sessionId)
            ->willReturn(null);

        $service = new DefaultGameService(
            $this->repositoryMock,
            $this->factoryMock,
            $this->loggerMock
        );

        // Assert & Act
        $this->expectException(InvalidArgumentException::class);
        $service->cashOut($sessionId);
    }

    /**
     * Test cash out with already closed session
     */
    public function testCashOutWithClosedSession(): void
    {
        // Arrange
        $sessionId = 'closed_session';
        $balance = 50.0;

        // Create closed session
        $session = new GameSessionDTO(
            $sessionId,
            $balance,
            0.0,
            0.0,
            new DateTimeImmutable(),
            new DateTimeImmutable(),
            false // Session is already closed
        );

        // Setup repository mock to return the closed session
        $this->repositoryMock->expects($this->once())
            ->method('getSession')
            ->with($sessionId)
            ->willReturn($session);

        // The service should reactivate the session and update it
        $this->repositoryMock->expects($this->exactly(2))
            ->method('updateSession')
            ->willReturnCallback(function(GameSessionDTO $updatedSession) use ($session, $sessionId) {
                // First call - reactivate session
                static $callCount = 0;
                $callCount++;

                if ($callCount === 1) {
                    // First call should reactivate the session
                    $this->assertTrue($updatedSession->isActive);
                    $this->assertEquals($sessionId, $updatedSession->sessionId);
                } else {
                    // Second call should update session after cashout
                    $this->assertFalse($updatedSession->isActive);
                    $this->assertEquals(0.0, $updatedSession->balance);
                }

                return null;
            });

        $service = new DefaultGameService(
            $this->repositoryMock,
            $this->factoryMock,
            $this->loggerMock
        );

        // Act
        $result = $service->cashOut($sessionId);

        // Assert
        $this->assertInstanceOf(CashoutResultDTO::class, $result);
        $this->assertEquals($sessionId, $result->sessionId);
        $this->assertEquals($balance, $result->amount);
    }
}
