<?php

namespace Tests\Integration;

use Casino\Server\DTO\SpinRequestDTO;
use Casino\Server\Factories\DefaultGameFactory;
use Casino\Server\Repositories\MySQLGameRepository;
use Casino\Server\Services\DefaultGameService;
use Codeception\Test\Unit;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

class GameServiceIntegrationTest extends Unit
{
    private LoggerInterface $logger;
    private MySQLGameRepository $repository;
    private DefaultGameFactory $factory;
    private DefaultGameService $service;
    private string $testSessionPrefix = 'test_integration_';
    private Connection $connection;
    private array $testSessionIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        
        // Instead of getting the logger from the module, using NullLogger
        $this->logger = new NullLogger();
        
        $connectionParams = [
            'dbname' => 'casino_jackpot',
            'user' => 'casino_user',
            'password' => '$haHaR!',
            'host' => 'localhost',
            'driver' => 'pdo_mysql',
            'charset' => 'utf8mb4',
        ];
        
        $this->connection = DriverManager::getConnection($connectionParams);
        
        $this->repository = new MySQLGameRepository(
            $this->logger,
            3, // reelsCount
            1, // rowsCount
            1.0, // minBet
            10.0, // maxBet
            10.0, // initialCredits (default)
            $this->connection
        );
        
        $this->factory = new DefaultGameFactory(
            $this->logger,
            3, // reels count
            1, // rows count
            1.0, // min bet
            10.0, // max bet
            [
                'C' => 10, // Cherry
                'L' => 20, // Lemon
                'O' => 30, // Orange
                'W' => 40  // Watermelon
            ]
        );
        
        $this->service = new DefaultGameService(
            $this->repository,
            $this->factory,
            $this->logger,
            true // cheat enabled
        );
    }
    
    protected function tearDown(): void
    {
        if (!empty($this->testSessionIds)) {
            try {
                $placeholders = implode(',', array_fill(0, count($this->testSessionIds), '?'));
                $this->connection->executeStatement(
                    "DELETE FROM game_sessions WHERE session_id IN ($placeholders)",
                    $this->testSessionIds
                );
                
                $this->logger->info('Удалены тестовые сессии', [
                    'count' => count($this->testSessionIds),
                    'sessionIds' => $this->testSessionIds
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Ошибка при удалении тестовых сессий', [
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Альтернативный вариант - удаление по префиксу
        try {
            $this->connection->executeStatement(
                "DELETE FROM game_sessions WHERE session_id LIKE ?",
                [$this->testSessionPrefix . '%']
            );
            
            $this->logger->info('Удалены тестовые сессии по префиксу', [
                'prefix' => $this->testSessionPrefix
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при удалении тестовых сессий по префиксу', [
                'error' => $e->getMessage()
            ]);
        }
        
        parent::tearDown();
    }

    public function testCreateSessionAndSpin(): void
    {
        $initialBalance = 20.0;
        $session = $this->service->createSession($initialBalance);
        
        $this->testSessionIds[] = $session->sessionId;
        
        $this->assertNotNull($session);
        $this->assertEquals($initialBalance, $session->balance);
        
        $betAmount = 1.0;
        $spinRequest = new SpinRequestDTO($betAmount);
        $spinResult = $this->service->processSpin($session->sessionId, $spinRequest);
        
        $this->assertNotNull($spinResult);
        $this->assertEquals($betAmount, $spinResult->betAmount);
        
        $updatedSession = $this->repository->getSession($session->sessionId);
        $this->assertEquals($initialBalance - $betAmount + $spinResult->winAmount, $updatedSession->balance);
    }
    
    public function testCashOut(): void
    {
        $initialBalance = 30.0;
        $session = $this->service->createSession($initialBalance);
        
        $this->testSessionIds[] = $session->sessionId;
        
        $cashoutResult = $this->service->cashOut($session->sessionId);
        
        $this->assertNotNull($cashoutResult);
        $this->assertEquals($initialBalance, $cashoutResult->amount);
        
        $updatedSession = $this->repository->getSession($session->sessionId);
        $this->assertFalse($updatedSession->isActive);
        $this->assertEquals(0.0, $updatedSession->balance);
    }
}