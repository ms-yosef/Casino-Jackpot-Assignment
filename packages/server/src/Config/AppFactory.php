<?php

declare(strict_types=1);

namespace Casino\Server\Config;

use Casino\Server\Routes;
use Casino\Server\Middleware\CorsMiddleware;
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
        // Add CORS middleware
        $app->add(new CorsMiddleware());

        // Add OPTIONS route for CORS preflight requests
        $app->options('/{routes:.+}', function ($request, $response) {
            return $response;
        });

        $displayErrorDetails = filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN);
        $app->addErrorMiddleware(
            $displayErrorDetails,
            true,
            true
        );
    }

    private function configureRoutes(App $app): void
    {
        new Routes($app)->register();
    }
}