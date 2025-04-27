<?php
/**
 * Photo Description Generator
 * 
 * This script reads photos from an input folder, uses OpenRouter to classify them,
 * and saves the tags and descriptions to JSON metadata files.
 *
 * When called with a file path or URL as an argument, it will analyze that single image
 * and return the metadata as JSON.
 *
 * Using king23/di for dependency injection.
 */

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use PhotoDesc\Container;
use PhotoDesc\PhotoProcessor;

try {
    // Load environment variables
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    
    // Create and configure the dependency injection container
    $container = Container::create();
    
    // Get the photo processor
    /** @var PhotoProcessor $processor */
    $processor = $container->get(PhotoProcessor::class);
    
    // Check if an argument was provided
    if (isset($argv[1])) {
        // Process a single image file or URL
        $path = $argv[1];
        $result = $processor->processSingle($path);
        
        if ($result) {
            // Output as JSON
            echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
        } else {
            echo "Error: Failed to process image.\n";
            exit(1);
        }
    } else {
        // Run the default batch processing
        $processor->run();
    }
    
} catch (Exception $e) {
    echo "Error: {$e->getMessage()}\n";
    exit(1);
}
