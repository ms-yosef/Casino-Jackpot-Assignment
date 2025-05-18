<?php

/**
 * Casino Jackpot Slot Machine API
 *
 * Main entry point for the API
 */

declare(strict_types=1);

// Set the absolute path to the project root
define('ROOT_DIR', dirname(__DIR__));

// Require the Composer autoloader
require ROOT_DIR . '/vendor/autoload.php';

// Basic response for testing
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'message' => 'Casino Jackpot Slot Machine API',
    'documentation' => '/swagger/'
]);
