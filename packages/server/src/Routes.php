<?php

declare(strict_types=1);

namespace Casino\Server;

use Casino\Server\Controllers\GameController;
use Slim\App;

class Routes
{
    private App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function register(): void
    {
        $this->app->get('/api/game/config', [GameController::class, 'getConfig']);
        $this->app->get('/api/game/ping', [GameController::class, 'ping']);
        $this->app->post('/api/game/session', [GameController::class, 'createSession']);
        $this->app->post('/api/game/spin', [GameController::class, 'processSpin']);
        $this->app->post('/api/game/cashout', [GameController::class, 'cashOut']);
    }
}