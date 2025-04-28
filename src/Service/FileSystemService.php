<?php

namespace PhotoDesc\Service;

use Psr\Log\LoggerInterface;


/**
 * Service for handling file system operations
 */
class FileSystemService
{
    private string $inputFolder;
    private string $outputFolder;
    private array $supportedExtensions;
    private LoggerInterface $logger;

    /**
     * Constructor
     * 
     * @param LoggerInterface $logger Logger instance
     * @param string $inputFolder Directory where photos are stored
     * @param string $outputFolder Directory where JSON metadata will be saved
     * @param array $supportedExtensions List of supported image extensions
     */
    public function __construct(
        LoggerInterface $logger,
        string $inputFolder,
        string $outputFolder,
        array $supportedExtensions
    ) {
        $this->logger = $logger;
        $this->inputFolder = $inputFolder;
        $this->outputFolder = $outputFolder;
        $this->supportedExtensions = $supportedExtensions;
    }

    /**
     * Initialize input and output directories
     * 
     * @return void
     */
    public function initializeFolders(): void
    {
        $inputFolder = $this->inputFolder;
        $outputFolder = $this->outputFolder;

        if (!file_exists($inputFolder)) {
            mkdir($inputFolder, 0755, true);
            $this->logger->info("Created input folder: $inputFolder");
        }

        if (!file_exists($outputFolder)) {
            mkdir($outputFolder, 0755, true);
            $this->logger->info("Created output folder: $outputFolder");
        }
    }

    /**
     * Get a list of images in the input folder
     * 
     * @return array List of image filenames
     */
    public function getImagesList(): array
    {
        $supportedExtensions = $this->supportedExtensions;
        $inputFolder = $this->inputFolder;
        $images = [];

        foreach (scandir($inputFolder) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($extension, $supportedExtensions)) {
                $images[] = $file;
            }
        }

        $this->logger->info('Found ' . count($images) . ' images to process');
        
        return $images;
    }

    /**
     * Read an image file, resize if needed, and convert it to base64
     * 
     * @param string $imageName Image filename
     * @return string Base64 encoded image data
     * @throws \Exception If the image can't be processed
     */
    public function readImageToBase64(string $imageName): string
    {
        $imagePath = $this->inputFolder . '/' . $imageName;
        $extension = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));
        
        // Check the file size
        $fileSize = filesize($imagePath);
        $maxSize = 5 * 1024 * 1024; // 5MB in bytes
        
        if ($fileSize <= $maxSize) {
            // If the image is already under the size limit, just return it
            $this->logger->info("Image {$imageName} is {$fileSize} bytes, under the 5MB limit");
            $imageData = file_get_contents($imagePath);
            return base64_encode($imageData);
        }
        
        // Need to resize the image
        $this->logger->info("Image {$imageName} is {$fileSize} bytes, resizing to fit under 5MB limit");
        
        if (!extension_loaded('gd')) {
            throw new \Exception("The GD extension is required for image resizing");
        }
        
        // Load the image based on its format
        $sourceImage = null;
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $sourceImage = imagecreatefromjpeg($imagePath);
                break;
            case 'png':
                $sourceImage = imagecreatefrompng($imagePath);
                break;
            case 'gif':
                $sourceImage = imagecreatefromgif($imagePath);
                break;
            case 'webp':
                $sourceImage = imagecreatefromwebp($imagePath);
                break;
            default:
                throw new \Exception("Unsupported image format: {$extension}");
        }
        
        if (!$sourceImage) {
            throw new \Exception("Failed to load image: {$imagePath}");
        }
        
        // Get the original dimensions
        $origWidth = imagesx($sourceImage);
        $origHeight = imagesy($sourceImage);
        
        // Calculate new dimensions (start with 75% reduction and adjust if needed)
        $scaleFactor = 0.7; // 70% of original size
        $newWidth = (int)($origWidth * $scaleFactor);
        $newHeight = (int)($origHeight * $scaleFactor);
        
        // Create a new image with the new dimensions
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Handle transparency for PNG
        if ($extension === 'png') {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
            imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        // Resize the image
        imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
        
        // Save to a temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'img_') . '.' . $extension;
        
        // Save based on the format
        $quality = 85; // Adjust quality for jpg/webp
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($newImage, $tempFile, $quality);
                break;
            case 'png':
                // PNG quality is 0-9, lower is better quality but larger file
                imagepng($newImage, $tempFile, 6);
                break;
            case 'gif':
                imagegif($newImage, $tempFile);
                break;
            case 'webp':
                imagewebp($newImage, $tempFile, $quality);
                break;
        }
        
        // Free memory
        imagedestroy($sourceImage);
        imagedestroy($newImage);
        
        // Check if we need to resize again
        $newSize = filesize($tempFile);
        if ($newSize > $maxSize) {
            // Still too big, try more aggressive compression for JPG/WEBP
            if ($extension === 'jpg' || $extension === 'jpeg' || $extension === 'webp') {
                $this->logger->info("Applying more aggressive compression to reduce file size further");
                $sourceImage = ($extension === 'webp') ? imagecreatefromwebp($tempFile) : imagecreatefromjpeg($tempFile);
                
                // Try with lower quality
                $quality = 50;
                if ($extension === 'webp') {
                    imagewebp($sourceImage, $tempFile, $quality);
                } else {
                    imagejpeg($sourceImage, $tempFile, $quality);
                }
                
                imagedestroy($sourceImage);
            }
        }
        
        $resizedData = file_get_contents($tempFile);
        unlink($tempFile); // Clean up temporary file
        
        $newSize = strlen($resizedData);
        $this->logger->info("Resized image to {$newSize} bytes");
        
        return base64_encode($resizedData);
    }

    /**
     * Check if an image has already been processed and is up-to-date
     * 
     * @param string $imageName Image filename
     * @return bool True if already processed and up-to-date, false otherwise
     */
    public function isImageProcessed(string $imageName): bool
    {
        $imagePath = $this->inputFolder . '/' . $imageName;
        $outputBasename = pathinfo($imageName, PATHINFO_FILENAME);
        $outputJsonPath = $this->outputFolder . '/' . $outputBasename . '.json';
        
        // If JSON file doesn't exist, the image hasn't been processed
        if (!file_exists($outputJsonPath)) {
            return false;
        }
        
        // Check if image was modified after the JSON file was created
        $imageModTime = filemtime($imagePath);
        $jsonModTime = filemtime($outputJsonPath);
        
        if ($imageModTime > $jsonModTime) {
            $this->logger->info("Image {$imageName} has been modified since last processing");
            return false;
        }
        
        return true;
    }

    /**
     * Save image metadata to a JSON file
     * 
     * @param string $imageName Image filename
     * @param array $metadata Metadata to save
     * @return bool True on success, false on failure
     */
    public function saveMetadata(string $imageName, array $metadata): bool
    {
        $outputBasename = pathinfo($imageName, PATHINFO_FILENAME);
        $outputJsonPath = $this->outputFolder . '/' . $outputBasename . '.json';
        
        $result = file_put_contents(
            $outputJsonPath, 
            json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        
        if ($result !== false) {
            $this->logger->info("Saved metadata for {$imageName}");
            return true;
        } else {
            $this->logger->error("Failed to save metadata for {$imageName}");
            return false;
        }
    }
}
