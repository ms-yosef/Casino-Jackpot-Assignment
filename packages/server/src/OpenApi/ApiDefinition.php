<?php

namespace Casino\Server\OpenApi;

use OpenApi\Attributes as OA;

#[OA\OpenApi(
    info: new OA\Info(
        version: '1.0.0',
        title: 'Casino Jackpot API',
        description: 'API for Casino Jackpot Slot Machine game',
        contact: new OA\Contact(
            name: 'API Support',
            email: 'yosef.trachtenberg@gmail.com'
        )
    ),
    servers: [
        new OA\Server(
            url: 'http://localhost:8081',
            description: 'Development server'
        )
    ]
)]
#[OA\Tag(
    name: 'Game',
    description: 'Game operations'
)]
class ApiDefinition
{
    #[OA\Schema(
        schema: 'GameConfig',
        title: 'Game Configuration',
        description: 'Configuration for the Casino Jackpot game',
        properties: [
            new OA\Property(property: 'initialCredits', type: 'integer', example: 10),
            new OA\Property(property: 'spinCost', type: 'integer', example: 1),
            new OA\Property(property: 'symbols', type: 'array', items: new OA\Items(type: 'string')),
            new OA\Property(
                property: 'symbolValues',
                type: 'object',
                properties: [
                    new OA\Property(property: 'C', type: 'integer', example: 10),
                    new OA\Property(property: 'L', type: 'integer', example: 20),
                    new OA\Property(property: 'O', type: 'integer', example: 30),
                    new OA\Property(property: 'W', type: 'integer', example: 40)
                ]
            ),
            new OA\Property(
                property: 'symbolNames',
                type: 'object',
                properties: [
                    new OA\Property(property: 'C', type: 'string', example: 'Cherry'),
                    new OA\Property(property: 'L', type: 'string', example: 'Lemon'),
                    new OA\Property(property: 'O', type: 'string', example: 'Orange'),
                    new OA\Property(property: 'W', type: 'string', example: 'Watermelon')
                ]
            )
        ]
    )]
    public function gameConfigSchema()
    {
    }

    #[OA\Get(
        path: '/api/game/config',
        operationId: 'getGameConfig',
        summary: 'Get game configuration',
        tags: ['Game'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Game configuration',
                content: new OA\JsonContent(ref: '#/components/schemas/GameConfig')
            )
        ]
    )]
    public function getConfig()
    {
    }

    #[OA\Schema(
        schema: 'Session',
        title: 'Game Session',
        description: 'Session information for the Casino Jackpot game',
        properties: [
            new OA\Property(property: 'sessionId', type: 'string', example: '550e8400-e29b-41d4-a716-446655440000'),
            new OA\Property(property: 'credits', type: 'integer', example: 10)
        ]
    )]
    public function sessionSchema()
    {
    }

    #[OA\Post(
        path: '/api/game/session',
        operationId: 'createGameSession',
        summary: 'Create a new game session',
        tags: ['Game'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Session created successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/Session')
            )
        ]
    )]
    public function createSession()
    {
    }

    #[OA\Schema(
        schema: 'SpinRequest',
        title: 'Spin Request',
        description: 'Request to process a spin',
        required: ['sessionId'],
        properties: [
            new OA\Property(property: 'sessionId', type: 'string', example: '550e8400-e29b-41d4-a716-446655440000')
        ]
    )]
    public function spinRequestSchema()
    {
    }

    #[OA\Schema(
        schema: 'SpinResult',
        title: 'Spin Result',
        description: 'Result of a spin',
        properties: [
            new OA\Property(property: 'symbols', type: 'array', items: new OA\Items(type: 'string')),
            new OA\Property(property: 'win', type: 'integer', example: 0),
            new OA\Property(property: 'credits', type: 'integer', example: 9),
            new OA\Property(property: 'message', type: 'string', example: 'Sorry, you lost. Try again!')
        ]
    )]
    public function spinResultSchema()
    {
    }

    #[OA\Post(
        path: '/api/game/spin',
        operationId: 'processGameSpin',
        summary: 'Process a spin',
        tags: ['Game'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/SpinRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Spin result',
                content: new OA\JsonContent(ref: '#/components/schemas/SpinResult')
            )
        ]
    )]
    public function processSpin()
    {
    }

    #[OA\Schema(
        schema: 'CashOutRequest',
        title: 'Cash Out Request',
        description: 'Request to cash out and close the session',
        required: ['sessionId'],
        properties: [
            new OA\Property(property: 'sessionId', type: 'string', example: '550e8400-e29b-41d4-a716-446655440000')
        ]
    )]
    public function cashOutRequestSchema()
    {
    }

    #[OA\Schema(
        schema: 'CashOutResult',
        title: 'Cash Out Result',
        description: 'Result of a cash out',
        properties: [
            new OA\Property(property: 'credits', type: 'integer', example: 15),
            new OA\Property(property: 'spins', type: 'integer', example: 5),
            new OA\Property(property: 'duration', type: 'integer', example: 120),
            new OA\Property(property: 'message', type: 'string', example: 'Successfully cashed out 15 credits!')
        ]
    )]
    public function cashOutResultSchema()
    {
    }

    #[OA\Post(
        path: '/api/game/cashout',
        operationId: 'cashOutGameSession',
        summary: 'Cash out and close the session',
        tags: ['Game'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CashOutRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Cash out result',
                content: new OA\JsonContent(ref: '#/components/schemas/CashOutResult')
            )
        ]
    )]
    public function cashOut()
    {
    }
}
