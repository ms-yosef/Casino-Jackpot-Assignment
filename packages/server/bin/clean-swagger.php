<?php

/**
 * Clean Swagger directory
 */

$swaggerDir = __DIR__ . '/../public/swagger';

if (is_dir($swaggerDir)) {
    echo "Removing existing Swagger directory: {$swaggerDir}\n";

    $files = glob($swaggerDir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }

    rmdir($swaggerDir);

    echo "Swagger directory removed successfully.\n";
} else {
    echo "Swagger directory does not exist.\n";
}

echo "Ready to generate fresh Swagger documentation.\n";
