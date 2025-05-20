<?php

declare(strict_types=1);

namespace Casino\Server\Controllers;

use Casino\Server\DTO\SpinRequestDTO;
use Casino\Server\DTO\SpinResultDTO;
use Casino\Server\Interfaces\Service\GameServiceInterface;
use Exception;
use JsonException;
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
                'symbols' => $config->cardsData,
                'payouts' => $config->cardsData,
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
     * @throws JsonException
     */
    public function processSpin(Request $request, Response $response): Response
    {
        $body = $request->getBody()->getContents();
        $data = json_decode($body, true);
        
        // Parse request data
        $sessionId = $data['sessionId'] ?? null;
        $betAmount = (float)($data['betAmount'] ?? 1.0);
        $linesCount = $data['linesCount'] ?? null;

        if (!$sessionId) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Session ID is required'
            ], 400);
        }

        $this->logger->info('Processing spin request', [
            'sessionId' => $sessionId,
            'betAmount' => $betAmount
        ]);

        try {
            // Get session
            $session = $this->gameService->getSession($sessionId);

            // Get game configuration
            $config = $this->gameService->getGameConfig();

            // Generate spin result
            $spinRequest = new SpinRequestDTO($betAmount, $linesCount);
            $result = $this->gameService->processSpin($sessionId, $spinRequest);

            // Get updated session
            $updatedSession = $this->gameService->getSession($sessionId);

            $this->logger->info('Processed spin', [
                'sessionId' => $sessionId,
                'betAmount' => $betAmount,
                'winAmount' => $result->winAmount,
                'isWin' => $result->isWin()
            ]);

            if ($updatedSession->balance <= 0) {
                try {
                    $updatedSession->isActive = false;
                    $this->gameService->updateSession($updatedSession);
                    
                    $this->logger->info('Session closed due to zero balance', [
                        'sessionId' => $sessionId,
                        'balance' => $updatedSession->balance,
                        'totalBet' => $updatedSession->totalBet,
                        'totalWin' => $updatedSession->totalWin,
                        'netProfit' => $updatedSession->totalWin - $updatedSession->totalBet
                    ]);
                    
                    return $this->jsonResponse($response, [
                        'success' => true,
                        'data' => $result,
                        'currentBalance' => 0,
                        'sessionClosed' => true,
                        'message' => 'Your credits have reached zero. Session closed.'
                    ]);
                } catch (Exception $e) {
                    $this->logger->error('Error auto-closing session', [
                        'sessionId' => $sessionId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $result,
                'currentBalance' => $updatedSession->balance
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error processing spin', [
                'sessionId' => $sessionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $statusCode = 500; // by default - Internal Server Error
            
            $validationErrorMessages = [
                'Bet amount must be between',
                'Insufficient funds',
                'Invalid session',
                'Session expired'
            ];
            
            foreach ($validationErrorMessages as $errorMessage) {
                if (str_contains($e->getMessage(), $errorMessage)) {
                    $statusCode = 400; // Bad Request
                    break;
                }
            }

            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Error processing spin: ' . $e->getMessage()
            ], $statusCode);
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

    /**
     * Simple ping endpoint to check server availability
     *
     * @param Request $request HTTP request
     * @param Response $response HTTP response
     * @return Response
     */
    public function ping(Request $request, Response $response): Response
    {
        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Server is available',
            'timestamp' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')
        ]);
    }

    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
