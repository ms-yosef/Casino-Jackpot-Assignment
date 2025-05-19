<?php

declare(strict_types=1);

namespace Casino\Server\Config;

use Casino\Server\Routes;
use Slim\Factory\AppFactory as SlimAppFactory;
use Slim\App;

class AppFactory
{
    public function createApp(): App
    {
        $container = (new ContainerFactory())->createContainer();

        $app = SlimAppFactory::createFromContainer($container);

        $this->configureMiddleware($app);

        $this->configureRoutes($app);

        return $app;
    }

    private function configureMiddleware(App $app): void
    {
        $app->addErrorMiddleware(
            $_ENV['APP_DEBUG'] ?? false,
            true,
            true
        );
    }

    private function configureRoutes(App $app): void
    {
        new Routes($app)->register();
    }
}