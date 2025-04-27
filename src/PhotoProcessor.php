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
}
