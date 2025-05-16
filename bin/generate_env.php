<?php
declare(strict_types=1);

define('ROOT_DIR', dirname(__DIR__));

if ($argc < 2) {
    echo "Using: php generate_env.php [environment] [output_file]\n";
    echo "Example: php generate_env.php dev .env.dev\n";
    echo "Available environments: dev, prod, test\n";
    exit(1);
}

$environment = $argv[1] ?? 'dev';
$outputFile = $argv[2] ?? ".env.{$environment}";

$template = file_get_contents(ROOT_DIR . '/.env.example');
if ($template === false) {
    echo "Error: Can't read file .env.example\n";
    exit(1);
}

$environments = [
    'dev' => [
        'APP_ENV' => 'development',
        'APP_DEBUG' => 'true',
        'APP_URL' => 'http://localhost:8080',
        'DB_HOST' => 'localhost',
        'DB_NAME' => 'casino_jackpot_dev',
        'DB_USER' => 'root',
        'DB_PASS' => '',
        'LOG_LEVEL' => 'debug',
        'CHEAT_ENABLED' => 'true',
    ],
    'test' => [
        'APP_ENV' => 'testing',
        'APP_DEBUG' => 'true',
        'APP_URL' => 'http://localhost:8080',
        'DB_HOST' => 'localhost',
        'DB_NAME' => 'casino_jackpot_test',
        'DB_USER' => 'root',
        'DB_PASS' => '',
        'LOG_LEVEL' => 'debug',
        'CHEAT_ENABLED' => 'true',
    ],
    'prod' => [
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'APP_URL' => 'https://casino-jackpot.example.com',
        'DB_HOST' => 'localhost',
        'DB_NAME' => 'casino_jackpot_prod',
        'DB_USER' => 'casino_user',
        'DB_PASS' => 'change_me_in_production',
        'LOG_LEVEL' => 'error',
        'CHEAT_ENABLED' => 'false',
    ],
];

if (!isset($environments[$environment])) {
    echo "Error: Undefined environment '{$environment}'\n";
    exit(1);
}

$config = $environments[$environment];
$lines = explode("\n", $template);
$result = [];

foreach ($lines as $line) {
    if (empty($line) || strpos($line, '#') === 0) {
        $result[] = $line;
        continue;
    }

    if (strpos($line, '=') !== false) {
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);

        if (isset($config[$key])) {
            $result[] = "{$key}={$config[$key]}";
        } else {
            $result[] = $line;
        }
    } else {
        $result[] = $line;
    }
}

$outputPath = ROOT_DIR . '/' . $outputFile;
if (file_put_contents($outputPath, implode("\n", $result)) === false) {
    echo "Error: Impossible to create/write file {$outputFile}.\n";
    exit(1);
}

echo "File {$outputFile} created.\n";
