<?php
/**
 * Photo Description Generator
 * 
 * This script reads photos from an input folder, uses OpenRouter to classify them,
 * and saves the tags and descriptions to JSON metadata files.
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
    
    // Get the photo processor and run it
    /** @var PhotoProcessor $processor */
    $processor = $container->get(PhotoProcessor::class);
    $processor->run();
    
} catch (Exception $e) {
    echo "Error: {$e->getMessage()}\n";
    exit(1);
}
