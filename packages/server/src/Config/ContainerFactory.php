<?php

declare(strict_types=1);

namespace Casino\Server\Config;

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;

class ContainerFactory
{
    /**
     * @throws \Exception
     */
    public function createContainer(): ContainerInterface
    {
        $builder = new ContainerBuilder();

        // Enable compilation to improve productivity in production
        if ($_ENV['APP_ENV'] === 'prod') {
            $builder->enableCompilation(__DIR__ . '/../../var/cache');
            $builder->writeProxiesToFile(true, __DIR__ . '/../../var/cache/proxies');
        }

        $builder->addDefinitions($this->getDefinitions());

        return $builder->build();
    }

    private function getDefinitions(): array
    {
        return require __DIR__ . '/definitions.php';
    }
}