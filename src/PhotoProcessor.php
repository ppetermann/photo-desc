<?php

namespace PhotoDesc;

use Psr\Log\LoggerInterface;
use PhotoDesc\Service\FileSystemService;
use PhotoDesc\Service\OpenRouterService;

/**
 * Main photo processor application class
 */
class PhotoProcessor
{
    private FileSystemService $fileSystemService;
    private OpenRouterService $openRouterService;
    private LoggerInterface $logger;

    /**
     * Constructor
     * 
     * @param FileSystemService $fileSystemService
     * @param OpenRouterService $openRouterService
     * @param LoggerInterface $logger
     */
    public function __construct(
        FileSystemService $fileSystemService,
        OpenRouterService $openRouterService,
        LoggerInterface $logger
    ) {
        $this->fileSystemService = $fileSystemService;
        $this->openRouterService = $openRouterService;
        $this->logger = $logger;
    }

    /**
     * Run the photo processing application
     * 
     * @return void
     */
    public function run(): void
    {
        $this->logger->info('Starting photo processing');
        
        // Initialize folders
        $this->fileSystemService->initializeFolders();
        
        // Get all images from input folder
        $images = $this->fileSystemService->getImagesList();
        
        // Process each image
        foreach ($images as $image) {
            // Skip if already processed
            if ($this->fileSystemService->isImageProcessed($image)) {
                $this->logger->info("Skipping {$image} (already processed)");
                continue;
            }
            
            $this->logger->info("Processing {$image}");
            
            // Convert image to base64
            $base64Image = $this->fileSystemService->readImageToBase64($image);
            
            // Call OpenRouter API to analyze the image
            $result = $this->openRouterService->classifyImage($base64Image, $image);
            
            if ($result) {
                // Save result to JSON file
                $this->fileSystemService->saveMetadata($image, $result);
            } else {
                $this->logger->error("Failed to process {$image}");
            }
            
            // Add a small delay to avoid rate limiting
            sleep(1);
        }
        
        $this->logger->info('Photo processing completed');
    }
    
    /**
     * Process a single image and return its metadata
     * 
     * @param string $path File path or URL to an image
     * @return array|null Metadata array or null on failure
     */
    public function processSingle(string $path): ?array
    {
        $this->logger->info("Processing single image: {$path}");
        
        try {
            // Check if it's a URL or file path
            if (filter_var($path, FILTER_VALIDATE_URL)) {
                return $this->processImageUrl($path);
            } else {
                return $this->processImageFile($path);
            }
        } catch (\Exception $e) {
            $this->logger->error("Error processing image: {$e->getMessage()}");
            return null;
        }
    }
    
    /**
     * Process an image from a local file path
     * 
     * @param string $filePath Path to the image file
     * @return array|null Metadata array or null on failure
     * @throws \Exception If file doesn't exist or can't be read
     */
    private function processImageFile(string $filePath): ?array
    {
        // Check if file exists
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: {$filePath}");
        }
        
        // Read image file to base64
        $imageData = file_get_contents($filePath);
        $base64Image = base64_encode($imageData);
        
        // Call OpenRouter API to analyze the image
        return $this->openRouterService->classifyImage($base64Image, basename($filePath));
    }
    
    /**
     * Process an image from a URL
     * 
     * @param string $url URL of the image
     * @return array|null Metadata array or null on failure
     * @throws \Exception If URL can't be accessed
     */
    private function processImageUrl(string $url): ?array
    {
        // Use cURL for better error handling and compatibility
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        ]);
        
        $imageData = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($imageData === false || $httpCode >= 400) {
            throw new \Exception("Unable to access URL: {$url}" . ($error ? " - {$error}" : " - HTTP {$httpCode}"));
        }
        
        // Extract filename from URL for mime type detection
        $urlPath = parse_url($url, PHP_URL_PATH);
        $filename = basename($urlPath ?: 'image.jpg');
        
        // Convert to base64
        $base64Image = base64_encode($imageData);
        
        // Call OpenRouter API to analyze the image
        return $this->openRouterService->classifyImage($base64Image, $filename);
    }
}
