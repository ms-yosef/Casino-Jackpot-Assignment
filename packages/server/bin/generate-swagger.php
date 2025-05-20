<?php

/**
 * Swagger documentation generator
 *
 * This script generates OpenAPI documentation for the Casino Jackpot API
 */

error_reporting(E_ERROR | E_PARSE);

require_once __DIR__ . '/../vendor/autoload.php';

use OpenApi\Generator;

try {
    // Paths to scan
    $paths = [
        __DIR__ . '/../src/OpenApi',
        __DIR__ . '/../src/Controllers',
        __DIR__ . '/../src/Schema',
        __DIR__ . '/../src/Routes.php',
    ];
    $outputPath = __DIR__ . '/../public/swagger';

    // Ensure the output directory exists
    if (!is_dir($outputPath)) {
        if (!mkdir($outputPath, 0755, true) && !is_dir($outputPath)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $outputPath));
        }
    }

    // Generate OpenAPI documentation
    $openapi = Generator::scan($paths);

    // Save as JSON
    file_put_contents(
        $outputPath . '/swagger.json',
        $openapi->toJson()
    );

    // Save as YAML
    file_put_contents(
        $outputPath . '/swagger.yaml',
        $openapi->toYaml()
    );

    // Copy Swagger UI files
    $swaggerUiDir = __DIR__ . '/../vendor/swagger-api/swagger-ui/dist';
    $swaggerUiFiles = [
        'swagger-ui.css',
        'swagger-ui-bundle.js',
        'swagger-ui-standalone-preset.js',
        'favicon-32x32.png',
        'favicon-16x16.png'
    ];

    foreach ($swaggerUiFiles as $file) {
        if (file_exists($swaggerUiDir . '/' . $file)) {
            copy(
                $swaggerUiDir . '/' . $file,
                $outputPath . '/' . $file
            );
        }
    }

    // Create index.html for Swagger UI
    $indexHtml = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Casino Jackpot API Documentation</title>
    <link rel="stylesheet" type="text/css" href="swagger-ui.css" />
    <link rel="icon" type="image/png" href="favicon-32x32.png" sizes="32x32" />
    <link rel="icon" type="image/png" href="favicon-16x16.png" sizes="16x16" />
    <style>
        html {
            box-sizing: border-box;
            overflow: -moz-scrollbars-vertical;
            overflow-y: scroll;
        }
        
        *,
        *:before,
        *:after {
            box-sizing: inherit;
        }
        
        body {
            margin: 0;
            background: #fafafa;
        }
    </style>
</head>

<body>
    <div id="swagger-ui"></div>

    <script src="swagger-ui-bundle.js"></script>
    <script src="swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function() {
            const ui = SwaggerUIBundle({
                url: "swagger.json",
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "StandaloneLayout"
            });
            window.ui = ui;
        };
    </script>
</body>
</html>
HTML;

    file_put_contents($outputPath . '/index.html', $indexHtml);

    echo "Swagger documentation generated successfully in {$outputPath}\n";
    echo "Access the documentation at http://localhost:8081/swagger/\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
