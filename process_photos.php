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
 * Command line arguments:
 * - Path to a file or URL: Process a single image and return JSON
 * - --log: Enable logging (by default logging is disabled for single image processing)
 *
 * Using king23/di for dependency injection.
 */

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PhotoDesc\Container;
use PhotoDesc\PhotoProcessor;

try {
    // Load environment variables
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    
    // Parse command line arguments
    $args = array_slice($argv, 1); // Remove script name
    $showLogs = false;
    $imagePath = null;
    
    foreach ($args as $arg) {
        if ($arg === '--log') {
            $showLogs = true;
        } elseif (strpos($arg, '--') !== 0) {
            $imagePath = $arg;
        }
    }
    
    // For single image processing without --log, we'll use a custom container that disables logging
    $quietMode = ($imagePath !== null && !$showLogs);
    
    // Create and configure the dependency injection container
    $container = Container::create($quietMode);
    
    // Get the photo processor
    /** @var PhotoProcessor $processor */
    $processor = $container->get(PhotoProcessor::class);
    
    // Check if an image path was provided
    if ($imagePath !== null) {
        // Process a single image file or URL
        $result = $processor->processSingle($imagePath);
        
        if ($result) {
            // Output as JSON - with no logging output, this will be clean JSON
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
