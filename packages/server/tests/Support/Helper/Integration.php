<?php

namespace Tests\Support\Helper;

use Codeception\Module;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Integration extends Module
{
    public function getLogger(): LoggerInterface
    {
        return new NullLogger();
    }
}
