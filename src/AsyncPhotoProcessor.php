<?php

namespace PhotoDesc;

use PhotoDesc\Service\AsyncOpenRouterService;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;

/**
 * Asynchronous image processor for use with ReactPHP
 */
class AsyncPhotoProcessor
{
    private AsyncOpenRouterService $openRouterService;
    private LoggerInterface $logger;

    /**
     * Constructor
     * 
     * @param AsyncOpenRouterService $openRouterService Async service for OpenRouter API
     * @param LoggerInterface $logger Logger instance
     */
    public function __construct(
        AsyncOpenRouterService $openRouterService,
        LoggerInterface $logger
    ) {
        $this->openRouterService = $openRouterService;
        $this->logger = $logger;
    }

    /**
     * Process a single image and return its metadata asynchronously
     * 
     * @param string $path File path or URL to an image
     * @return PromiseInterface Promise resolving to array|null metadata or null on failure
     */
    public function processSingleAsync(string $path): PromiseInterface
    {
        $this->logger->info("Async processing of image: {$path}");
        
        // Check if it's a URL or file path
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $this->processImageUrlAsync($path);
        } else {
            return $this->processImageFileAsync($path);
        }
    }

    /**
     * Process an image from a local file path asynchronously
     * 
     * @param string $filePath Path to the image file
     * @return PromiseInterface Promise resolving to metadata array or rejecting with exception
     */
    /**
     * Check if a file exists - extracted for easier testing
     * 
     * @param string $path File path to check
     * @return bool Whether the file exists
     */
    protected function fileExists(string $path): bool
    {
        return file_exists($path);
    }
    
    /**
     * Read file contents - extracted for easier testing
     * 
     * @param string $path File path to read
     * @return string The file contents
     */
    protected function readFile(string $path): string
    {
        return file_get_contents($path);
    }
    
    private function processImageFileAsync(string $filePath): PromiseInterface
    {
        return \React\Promise\resolve()->then(function() use ($filePath) {
            // Check if file exists
            if (!$this->fileExists($filePath)) {
                throw new \Exception("File not found: {$filePath}");
            }
            
            // Read image file to base64
            $imageData = $this->readFile($filePath);
            if ($imageData === false) {
                throw new \Exception("Failed to read file: {$filePath}");
            }
            
            $base64Image = base64_encode($imageData);
            $imageName = basename($filePath);
            
            // Call OpenRouter API to analyze the image asynchronously
            return $this->openRouterService->classifyImage($base64Image, $imageName);
        })->otherwise(function (\Throwable $e) {
            $this->logger->error("Error processing image: {$e->getMessage()}");
            return null;
        });
    }

    /**
     * Process an image from a URL asynchronously
     * 
     * @param string $url URL to the image
     * @return PromiseInterface Promise resolving to metadata array or rejecting with exception
     */
    private function processImageUrlAsync(string $url): PromiseInterface
    {
        $browser = new \React\Http\Browser();
        
        return $browser->get($url)->then(
            function (\Psr\Http\Message\ResponseInterface $response) use ($url) {
                // Read image data
                $imageData = (string) $response->getBody();
                $base64Image = base64_encode($imageData);
                $imageName = basename(parse_url($url, PHP_URL_PATH));
                
                if (empty($imageName)) {
                    $imageName = 'image.jpg'; // Default name if URL doesn't contain a filename
                }
                
                // Call OpenRouter API to analyze the image asynchronously
                return $this->openRouterService->classifyImage($base64Image, $imageName);
            }
        )->otherwise(function (\Throwable $e) {
            $this->logger->error("Error processing image URL: {$e->getMessage()}");
            return null;
        });
    }
}
