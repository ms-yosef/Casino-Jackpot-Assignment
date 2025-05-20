<?php

declare(strict_types=1);

namespace Casino\Server\Tests\Unit\Repository;

use Casino\Server\DTO\GameConfigDTO;
use Casino\Server\DTO\GameSessionDTO;
use Casino\Server\DTO\SpinResultDTO;
use Casino\Server\Repositories\MySQLGameRepository;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for MySQLGameRepository
 * 
 * This test uses the actual database connection from .env
 */
class MySQLGameRepositoryTest extends Unit
{
    private LoggerInterface|MockObject $loggerMock;
    private Connection $connection;
    private MySQLGameRepository $repository;
    private string $testSessionPrefix;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        try {
            $connectionParams = [
                'dbname' => 'casino_jackpot',
                'user' => 'casino_user',
                'password' => '$haHaR!',
                'host' => 'localhost',
                'driver' => 'pdo_mysql',
                'charset' => 'utf8mb4',
            ];

            $this->connection = DriverManager::getConnection($connectionParams);
            
            $this->loggerMock = $this->createMock(LoggerInterface::class);
            
            $this->repository = new MySQLGameRepository(
                $this->loggerMock,
                3,
                1,
                1.0,
                10.0,
                100.0,
                $this->connection
            );
            
            $this->testSessionPrefix = 'test_session_' . time() . '_';
            
        } catch (\Exception $e) {
            $this->markTestSkipped("Failed to prepare the test: " . $e->getMessage());
        }
    }
    
    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            try {
                $this->connection->executeStatement(
                    'DELETE FROM game_sessions WHERE session_id LIKE ?',
                    [$this->testSessionPrefix . '%']
                );
                
                $this->connection->executeStatement(
                    'DELETE FROM spin_results WHERE session_id LIKE ?',
                    [$this->testSessionPrefix . '%']
                );
            } catch (\Exception $e) {
                echo "Warning: Failed to clear test data:: " . $e->getMessage() . "\n";
            }
        }
        
        parent::tearDown();
    }
    
    /**
     * Test: Get game config.
     */
    public function testGetGameConfig(): void
    {
        // Act
        $config = $this->repository->getGameConfig();
        
        // Assert
        $this->assertInstanceOf(GameConfigDTO::class, $config);
        $this->assertEquals(3, $config->reelsCount);
        $this->assertEquals(1, $config->rowsCount);
        $this->assertEquals(1.0, $config->minBet);
        $this->assertEquals(10.0, $config->maxBet);
    }
    
    /**
     * Test: Create a new session
     */
    public function testCreateSession(): void
    {
        // Act
        $session = $this->repository->createSession(50.0);
        
        $this->connection->executeStatement(
            'UPDATE game_sessions SET session_id = ? WHERE session_id = ?',
            [$this->testSessionPrefix . $session->sessionId, $session->sessionId]
        );
        
        $updatedSessionId = $this->testSessionPrefix . $session->sessionId;
        
        // Assert
        $this->assertInstanceOf(GameSessionDTO::class, $session);
        $this->assertEquals(50.0, $session->balance);
        $this->assertEquals(0.0, $session->totalBet);
        $this->assertEquals(0.0, $session->totalWin);
        $this->assertTrue($session->isActive);
        
        $data = $this->connection->executeQuery(
            'SELECT * FROM game_sessions WHERE session_id = ?',
            [$updatedSessionId]
        )->fetchAssociative();
        
        $this->assertNotFalse($data);
        $this->assertEquals(50.0, (float)$data['balance']);
    }
    
    /**
     * Test: Give an existed session
     */
    public function testGetSession(): void
    {
        // Arrange
        $session = $this->repository->createSession(75.0);
        
        $this->connection->executeStatement(
            'UPDATE game_sessions SET session_id = ? WHERE session_id = ?',
            [$this->testSessionPrefix . $session->sessionId, $session->sessionId]
        );
        
        $updatedSessionId = $this->testSessionPrefix . $session->sessionId;
        
        // Act
        $retrievedSession = $this->repository->getSession($updatedSessionId);
        
        // Assert
        $this->assertNotNull($retrievedSession);
        $this->assertEquals($updatedSessionId, $retrievedSession->sessionId);
        $this->assertEquals(75.0, $retrievedSession->balance);
        $this->assertTrue($retrievedSession->isActive);
    }
    
    /**
     * Test: Request a non-existent session
     */
    public function testGetNonExistentSession(): void
    {
        $nonExistentSessionId = $this->testSessionPrefix . 'non_existent_' . uniqid('', true);
        
        // Act
        $session = $this->repository->getSession($nonExistentSessionId);
        
        // Assert
        $this->assertNull($session);
    }
    
    /**
     * Test: Refresh the session
     */
    public function testUpdateSession(): void
    {
        // Arrange
        $session = $this->repository->createSession(100.0);
        
        $this->connection->executeStatement(
            'UPDATE game_sessions SET session_id = ? WHERE session_id = ?',
            [$this->testSessionPrefix . $session->sessionId, $session->sessionId]
        );
        
        $updatedSessionId = $this->testSessionPrefix . $session->sessionId;
        
        $updatedSession = new GameSessionDTO(
            $updatedSessionId,
            150.0,
            50.0,
            100.0,
            new \DateTimeImmutable(),
            null,
            true
        );
        
        $this->repository->updateSession($updatedSession);
        
        $retrievedSession = $this->repository->getSession($updatedSessionId);
        $this->assertNotNull($retrievedSession);
        $this->assertEquals(150.0, $retrievedSession->balance);
        $this->assertEquals(50.0, $retrievedSession->totalBet);
        $this->assertEquals(100.0, $retrievedSession->totalWin);
    }
    
    /**
     * Test: Save spin result
     */
    public function testSaveSpinResult(): void
    {
        // Arrange
        $session = $this->repository->createSession(100.0);
        
        $this->connection->executeStatement(
            'UPDATE game_sessions SET session_id = ? WHERE session_id = ?',
            [$this->testSessionPrefix . $session->sessionId, $session->sessionId]
        );
        
        $updatedSessionId = $this->testSessionPrefix . $session->sessionId;
        
        $spinResult = new SpinResultDTO(
            [['A', 'B', 'C']],
            5.0,
            10.0,
            [],
            [],
            new DateTimeImmutable()
        );
        
        $initialBalance = $session->balance;
        
        // Act
        $this->repository->saveSpinResult($updatedSessionId, $spinResult);
        
        // Assert
        $updatedSessionData = $this->connection->executeQuery(
            'SELECT * FROM game_sessions WHERE session_id = ?',
            [$updatedSessionId]
        )->fetchAssociative();
        
        $this->assertNotFalse($updatedSessionData);
        $expectedBalance = $initialBalance - $spinResult->betAmount + $spinResult->winAmount;
        $this->assertEquals($expectedBalance, (float)$updatedSessionData['balance']);
        $this->assertEquals($spinResult->betAmount, (float)$updatedSessionData['total_bet']);
        $this->assertEquals($spinResult->winAmount, (float)$updatedSessionData['total_win']);
    }
}
