<?php

declare(strict_types=1);

namespace Casino\Server\Controllers;

use Casino\Server\DTO\SpinRequestDTO;
use Casino\Server\Interfaces\Service\GameServiceInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

/**
 * Controller for game-related API endpoints.
 */
class GameController
{
    /**
     * @param GameServiceInterface $gameService Service for game operations
     * @param LoggerInterface $logger Logger for tracking operations
     */
    public function __construct(
        private readonly GameServiceInterface $gameService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Get game configuration
     *
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @return Response HTTP response with game configuration
     */
    public function getConfig(Request $request, Response $response): Response
    {
        $this->logger->info('Getting game configuration');
        
        $config = $this->gameService->getGameConfig();
        
        $responseData = [
            'success' => true,
            'data' => [
                'symbols' => $config->symbols,
                'payouts' => $config->payouts,
                'reelsCount' => $config->reelsCount,
                'rowsCount' => $config->rowsCount,
                'minBet' => $config->minBet,
                'maxBet' => $config->maxBet,
                'specialSymbolsPositions' => $config->specialSymbolsPositions,
            ]
        ];
        
        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Create a new game session
     *
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @return Response HTTP response with session information
     */
    public function createSession(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);
        
        if (!isset($data['initialBalance']) || !is_numeric($data['initialBalance']) || $data['initialBalance'] <= 0) {
            $this->logger->warning('Invalid initialBalance parameter', ['data' => $data]);
            
            $responseData = [
                'success' => false,
                'error' => 'Invalid initialBalance parameter. Must be a positive number.'
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        
        try {
            $initialBalance = (float) $data['initialBalance'];
            $session = $this->gameService->createSession($initialBalance);
            
            $responseData = [
                'success' => true,
                'data' => [
                    'sessionId' => $session->sessionId,
                    'balance' => $session->balance,
                    'createdAt' => $session->createdAt->format('c'),
                ]
            ];
            
            $this->logger->info('Created game session', [
                'sessionId' => $session->sessionId,
                'initialBalance' => $initialBalance
            ]);
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $this->logger->error('Error creating session', ['error' => $e->getMessage()]);
            
            $responseData = [
                'success' => false,
                'error' => 'Failed to create session: ' . $e->getMessage()
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Process a spin request
     *
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @return Response HTTP response with spin result
     */
    public function processSpin(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);
        
        if (!isset($data['sessionId']) || !is_string($data['sessionId'])) {
            $this->logger->warning('Invalid sessionId parameter', ['data' => $data]);
            
            $responseData = [
                'success' => false,
                'error' => 'Invalid sessionId parameter'
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        
        if (!isset($data['betAmount']) || !is_numeric($data['betAmount']) || $data['betAmount'] <= 0) {
            $this->logger->warning('Invalid betAmount parameter', ['data' => $data]);
            
            $responseData = [
                'success' => false,
                'error' => 'Invalid betAmount parameter. Must be a positive number.'
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        
        $sessionId = $data['sessionId'];
        $betAmount = (float) $data['betAmount'];
        $linesCount = isset($data['linesCount']) && is_numeric($data['linesCount']) ? (int) $data['linesCount'] : null;
        
        try {
            $spinRequest = new SpinRequestDTO($betAmount, $linesCount);
            $result = $this->gameService->processSpin($sessionId, $spinRequest);
            
            $responseData = [
                'success' => true,
                'data' => [
                    'reels' => $result->reels,
                    'betAmount' => $result->betAmount,
                    'winAmount' => $result->winAmount,
                    'isWin' => $result->isWin(),
                    'multiplier' => $result->getMultiplier(),
                    'winningLines' => $result->winningLines,
                    'features' => $result->features,
                    'timestamp' => $result->timestamp->format('c'),
                ]
            ];
            
            $this->logger->info('Processed spin', [
                'sessionId' => $sessionId,
                'betAmount' => $betAmount,
                'winAmount' => $result->winAmount
            ]);
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Error processing spin', [
                'sessionId' => $sessionId,
                'error' => $e->getMessage()
            ]);
            
            $responseData = [
                'success' => false,
                'error' => $e->getMessage()
            ];
            
            $status = str_contains($e->getMessage(), 'not found') ? 404 : 400;
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $this->logger->error('Error processing spin', [
                'sessionId' => $sessionId,
                'error' => $e->getMessage()
            ]);
            
            $responseData = [
                'success' => false,
                'error' => 'Failed to process spin: ' . $e->getMessage()
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Process a cashout request
     *
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @return Response HTTP response with cashout result
     */
    public function cashOut(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);
        
        if (!isset($data['sessionId']) || !is_string($data['sessionId'])) {
            $this->logger->warning('Invalid sessionId parameter', ['data' => $data]);
            
            $responseData = [
                'success' => false,
                'error' => 'Invalid sessionId parameter'
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        
        $sessionId = $data['sessionId'];
        
        try {
            $result = $this->gameService->cashOut($sessionId);
            
            $responseData = [
                'success' => true,
                'data' => [
                    'sessionId' => $result->sessionId,
                    'amount' => $result->amount,
                    'initialBalance' => $result->initialBalance,
                    'totalBet' => $result->totalBet,
                    'totalWin' => $result->totalWin,
                    'netProfit' => $result->getNetProfit(),
                    'isProfit' => $result->isProfit(),
                    'timestamp' => $result->timestamp->format('c'),
                ]
            ];
            
            $this->logger->info('Processed cashout', [
                'sessionId' => $sessionId,
                'amount' => $result->amount
            ]);
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Error processing cashout', [
                'sessionId' => $sessionId,
                'error' => $e->getMessage()
            ]);
            
            $responseData = [
                'success' => false,
                'error' => $e->getMessage()
            ];
            
            $status = str_contains($e->getMessage(), 'not found') ? 404 : 400;
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $this->logger->error('Error processing cashout', [
                'sessionId' => $sessionId,
                'error' => $e->getMessage()
            ]);
            
            $responseData = [
                'success' => false,
                'error' => 'Failed to process cashout: ' . $e->getMessage()
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
