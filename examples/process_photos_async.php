<?php

// Example of using photo-desc with ReactPHP
// For this example to work, you need to create a .env file
// in the root directory with your OpenRouter API key

require __DIR__ . '/../vendor/autoload.php';

use PhotoDesc\AsyncPhotoProcessor;
use PhotoDesc\Service\AsyncOpenRouterService;
use React\EventLoop\Loop;
use React\Http\Browser;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

// Load .env file
$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Create the event loop
$loop = Loop::get();

// Set up a logger
$logger = new Logger('async-photo-processor');
$logLevel = strtolower($_ENV['LOG_LEVEL'] ?? 'info') === 'debug' ? Logger::DEBUG : Logger::INFO;
$logger->pushHandler(new StreamHandler('php://stdout', $logLevel));

// Configure services
$apiKey = $_ENV['OPENROUTER_API_KEY'] ?? '';
$model = $_ENV['AI_MODEL'] ?? 'anthropic/claude-3-opus-20240229';

$logger->debug('Using API model: ' . $model);
$logger->debug('API key present: ' . (empty($apiKey) ? 'No' : 'Yes, starts with: ' . substr($apiKey, 0, 5) . '...'));

if (empty($apiKey)) {
    die("Error: OPENROUTER_API_KEY not found in .env file.\n");
}

// Create ReactPHP browser for HTTP requests
$browser = new Browser($loop);

// Create AsyncOpenRouterService
$openRouterService = new AsyncOpenRouterService(
    $browser,
    $logger,
    $apiKey,
    $model
);

// Create AsyncPhotoProcessor
$processor = new AsyncPhotoProcessor(
    $openRouterService,
    $logger
);

// Example: Process a local image
// You can replace this with an actual path to an image
$imagePath = $argv[1] ?? null;

if (!$imagePath) {
    die("Usage: php react_example.php <path_to_image_or_url>\n");
}

$logger->info("Starting to process image: {$imagePath}");

// Process the image asynchronously
$processor->processSingleAsync($imagePath)
    ->then(
        function ($metadata) use ($logger, $imagePath) {
            if ($metadata === null) {
                $logger->error("Failed to process image: {$imagePath}");
                return;
            }
            
            $logger->info("Image processed successfully!");
            $logger->info("Description: " . $metadata['description']);
            $logger->info("Tags: " . implode(', ', $metadata['tags']));
            
            // Output the result as JSON
            echo json_encode($metadata, JSON_PRETTY_PRINT) . "\n";
        },
        function ($error) use ($logger) {
            $logger->error("Error: " . $error->getMessage());
        }
    );

// Run the event loop
$logger->info("Running event loop...");
$loop->run();
