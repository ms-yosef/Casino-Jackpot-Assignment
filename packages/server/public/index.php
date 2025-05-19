<?php

/**
 * Casino Jackpot Slot Machine API
 *
 * Main entry point for the API
 */

declare(strict_types=1);

use Casino\Server\Config\AppFactory;

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Set the absolute path to the project root
define('ROOT_DIR', dirname(__DIR__));

/*
// Basic response for testing
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'message' => 'Casino Jackpot Slot Machine API',
    'documentation' => '/swagger/'
]);
*/

$app = (new AppFactory())->createApp();
$app->run();