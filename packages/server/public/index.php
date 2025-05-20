<?php

/**
 * Casino Jackpot Slot Machine API
 *
 * Main entry point for the API
 */

declare(strict_types=1);

use Casino\Server\Config\AppFactory;
use Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

// Set the absolute path to the project root
define('ROOT_DIR', dirname(__DIR__));

// Load environment variables
try {
    $appEnv = getenv('APP_ENVIRONMENT') ?: ($_SERVER['APP_ENVIRONMENT'] ?? 'dev');
    
    $envFile = ".env.{$appEnv}";
    
    if (!file_exists(ROOT_DIR . '/' . $envFile)) {
        $envFile = '.env';
    }
    
    $dotenv = Dotenv::createImmutable(ROOT_DIR, $envFile);
    $dotenv->load();
    
    // Make sure variables are also available through getenv()
    foreach ($_ENV as $key => $value) {
        putenv("$key=$value");
    }
    
    // Log successful loading
    error_log("Environment variables loaded successfully from {$envFile} in {$appEnv} mode");
} catch (\Exception $e) {
    error_log('Error loading environment variables: ' . $e->getMessage());
}

$app = (new AppFactory())->createApp();
$app->run();