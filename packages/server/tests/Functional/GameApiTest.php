<?php

namespace Tests\Functional;

use Codeception\Test\Unit;

class GameApiTest extends Unit
{
    /**
     * @var \Tests\Support\FunctionalTester
     */
    protected $tester;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testCreateSession(): void
    {
        $this->tester->haveHttpHeader('Content-Type', 'application/json');
        $this->tester->sendPOST('game/session', json_encode([
            'initialBalance' => 20.0
        ]));
        
        $this->tester->seeResponseCodeIs(200);
        $this->tester->seeResponseIsJson();
        
        $this->tester->seeResponseContainsJson([
            'success' => true
        ]);
        $this->tester->seeResponseJsonMatchesJsonPath('$.data.sessionId');
        $this->tester->seeResponseJsonMatchesJsonPath('$.data.balance');
    }
    
    public function testSpin(): void
    {
        $this->tester->haveHttpHeader('Content-Type', 'application/json');
        $this->tester->sendPOST('game/session', json_encode([
            'initialBalance' => 100.0
        ]));
        
        $this->tester->seeResponseCodeIs(200);
        $this->tester->seeResponseIsJson();
        
        $sessionResponse = json_decode($this->tester->grabResponse(), true);
        $sessionId = $sessionResponse['data']['sessionId'];
        
        $this->tester->haveHttpHeader('Content-Type', 'application/json');
        $this->tester->sendPOST('game/spin', json_encode([
            'sessionId' => $sessionId,
            'betAmount' => 1.0
        ]));
        
        $this->tester->seeResponseCodeIs(200);
        $this->tester->seeResponseIsJson();
        
        $spinResponse = json_decode($this->tester->grabResponse(), true);
        
        // Проверяем содержимое ответа
        $this->tester->seeResponseContainsJson([
            'success' => true
        ]);
        $this->tester->seeResponseJsonMatchesJsonPath('$.data.reels');
        $this->tester->seeResponseJsonMatchesJsonPath('$.data.betAmount');
        $this->tester->seeResponseJsonMatchesJsonPath('$.data.winAmount');
        
        $this->tester->validateSpinResult($spinResponse['data']);
        $this->tester->validateWinAmount($spinResponse['data']);
    }
    
    public function testCashOut(): void
    {
        $this->tester->haveHttpHeader('Content-Type', 'application/json');
        $this->tester->sendPOST('game/session', json_encode([
            'initialBalance' => 20.0
        ]));
        
        $this->tester->seeResponseCodeIs(200);
        $this->tester->seeResponseIsJson();
        
        $response = json_decode($this->tester->grabResponse(), true);
        
        $this->assertNotNull($response, 'Ответ не должен быть null');
        $this->assertArrayHasKey('data', $response, 'Ответ должен содержать data');
        $this->assertArrayHasKey('sessionId', $response['data'], 'Ответ должен содержать sessionId в data');
        
        $sessionId = $response['data']['sessionId'];
        
        $this->tester->haveHttpHeader('Content-Type', 'application/json');
        $this->tester->sendPOST('game/cashout', json_encode([
            'sessionId' => $sessionId
        ]));
        
        $this->tester->seeResponseCodeIs(200);
        $this->tester->seeResponseIsJson();
        
        $this->tester->seeResponseContainsJson([
            'success' => true
        ]);
        $this->tester->seeResponseJsonMatchesJsonPath('$.data.amount');
    }
    
    public function testInvalidBetAmount(): void
    {
        $this->tester->haveHttpHeader('Content-Type', 'application/json');
        $this->tester->sendPOST('game/session', json_encode([
            'initialBalance' => 20.0
        ]));
        
        $this->tester->seeResponseCodeIs(200);
        $this->tester->seeResponseIsJson();
        
        $response = json_decode($this->tester->grabResponse(), true);
        
        $this->assertNotNull($response);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('sessionId', $response['data']);
        
        $sessionId = $response['data']['sessionId'];
        
        $this->tester->haveHttpHeader('Content-Type', 'application/json');
        $this->tester->sendPOST('game/spin', json_encode([
            'sessionId' => $sessionId,
            'betAmount' => -1.0
        ]));
        
        $this->tester->seeResponseCodeIs(400);
        $this->tester->seeResponseIsJson();
        $this->tester->seeResponseContainsJson([
            'success' => false
        ]);
    }
    
    public function testInsufficientFunds(): void
    {
        $this->tester->haveHttpHeader('Content-Type', 'application/json');
        $this->tester->sendPOST('game/session', json_encode([
            'initialBalance' => 0.5
        ]));
        
        $this->tester->seeResponseCodeIs(200);
        $this->tester->seeResponseIsJson();
        
        $response = json_decode($this->tester->grabResponse(), true);
        
        $this->assertNotNull($response);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('sessionId', $response['data']);
        
        $sessionId = $response['data']['sessionId'];
        
        $this->tester->haveHttpHeader('Content-Type', 'application/json');
        $this->tester->sendPOST('game/spin', json_encode([
            'sessionId' => $sessionId,
            'betAmount' => 1.0
        ]));
        
        $this->tester->seeResponseCodeIs(400);
        $this->tester->seeResponseIsJson();
        $this->tester->seeResponseContainsJson([
            'success' => false
        ]);
    }
}