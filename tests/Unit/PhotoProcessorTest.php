<?php

namespace PhotoDesc\Tests\Unit;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use PhotoDesc\PhotoProcessor;

/**
 * Test subclass that overrides protected methods for testing
 */
class TestablePhotoProcessor extends PhotoProcessor
{
    private bool $fileExistsResult = true;
    private string $fileContents = 'test-file-contents';
    
    public function setFileExistsResult(bool $result): void
    {
        $this->fileExistsResult = $result;
    }
    
    public function setFileContents(string $contents): void
    {
        $this->fileContents = $contents;
    }
    
    protected function fileExists(string $path): bool
    {
        return $this->fileExistsResult;
    }
    
    protected function readFile(string $path): string
    {
        return $this->fileContents;
    }
}
use PhotoDesc\Service\FileSystemService;
use PhotoDesc\Service\OpenRouterService;
use Psr\Log\LoggerInterface;

class PhotoProcessorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private $mockFileSystemService;
    private $mockOpenRouterService;
    private $mockLogger;
    private $processor;
    private $testableProcessor;

    protected function setUp(): void
    {
        $this->mockFileSystemService = Mockery::mock(FileSystemService::class);
        $this->mockOpenRouterService = Mockery::mock(OpenRouterService::class);
        $this->mockLogger = Mockery::mock(LoggerInterface::class);
        
        $this->processor = new PhotoProcessor(
            $this->mockFileSystemService,
            $this->mockOpenRouterService,
            $this->mockLogger
        );
        
        // Create the testable version with overridden methods
        $this->testableProcessor = new TestablePhotoProcessor(
            $this->mockFileSystemService,
            $this->mockOpenRouterService,
            $this->mockLogger
        );
    }

    public function testRunCallsServicesInCorrectOrder()
    {
        // Test data
        $testImages = ['image1.jpg', 'image2.png'];
        $testBase64 = 'base64encodedimage';
        $testResult = ['description' => 'Test image', 'tags' => ['tag1', 'tag2']];
        
        // Set up expectations
        // Step 1: Initialize folders
        $this->mockFileSystemService->shouldReceive('initializeFolders')
            ->once();
        
        // Step 2: Get list of images
        $this->mockFileSystemService->shouldReceive('getImagesList')
            ->once()
            ->andReturn($testImages);
        
        // For each image, we should:
        // Step 3: Check if already processed (first one is, second one isn't)
        $this->mockFileSystemService->shouldReceive('isImageProcessed')
            ->once()
            ->with('image1.jpg')
            ->andReturn(true);
            
        $this->mockFileSystemService->shouldReceive('isImageProcessed')
            ->once()
            ->with('image2.png')
            ->andReturn(false);
        
        // Step 4: Read image to base64 (only for the second image)
        $this->mockFileSystemService->shouldReceive('readImageToBase64')
            ->once()
            ->with('image2.png')
            ->andReturn($testBase64);
        
        // Step 5: Call AI service to classify image
        $this->mockOpenRouterService->shouldReceive('classifyImage')
            ->once()
            ->with($testBase64, 'image2.png')
            ->andReturn($testResult);
        
        // Step 6: Save metadata
        $this->mockFileSystemService->shouldReceive('saveMetadata')
            ->once()
            ->with('image2.png', $testResult);
        
        // Logging expectations
        $this->mockLogger->shouldReceive('info')
            ->once()
            ->with('Starting photo processing');
            
        $this->mockLogger->shouldReceive('info')
            ->once()
            ->with('Skipping image1.jpg (already processed)');
            
        $this->mockLogger->shouldReceive('info')
            ->once()
            ->with('Processing image2.png');
            
        $this->mockLogger->shouldReceive('info')
            ->once()
            ->with('Photo processing completed');
        
        // Run the method we're testing
        $this->processor->run();
    }

    public function testProcessSingleSuccessful()
    {
        // Set up a test file path that we'll mock
        $path = '/path/to/test.jpg';
        $testData = 'test-image-data'; // This is the mock file content
        $base64Data = base64_encode($testData); // What we expect to be sent to the API
        $testResult = ['description' => 'Test image', 'tags' => ['tag1', 'tag2']];
        
        // Set up our testable processor
        $this->testableProcessor->setFileExistsResult(true);
        $this->testableProcessor->setFileContents($testData);
        
        // Logging expectations
        $this->mockLogger->shouldReceive('info')
            ->with("Processing single image: {$path}")
            ->once();
            
        // Error logging - allow but shouldn't be called
        $this->mockLogger->shouldReceive('error')
            ->zeroOrMoreTimes();

        // Setup mock for OpenRouterService - the key part is we expect it to be called with base64 encoded data
        $this->mockOpenRouterService->shouldReceive('classifyImage')
            ->with($base64Data, 'test.jpg')
            ->andReturn($testResult)
            ->once();
        
        // Run the method we're testing
        $result = $this->testableProcessor->processSingle($path);
        
        // Verify result
        $this->assertSame($testResult, $result);
    }

    public function testProcessSingleHandlesFileNotFound()
    {
        // Test data
        $path = '/path/to/nonexistent.jpg';
        
        // Configure our testable processor to simulate a file that doesn't exist
        $this->testableProcessor->setFileExistsResult(false);
        
        // Logging expectations
        $this->mockLogger->shouldReceive('info')
            ->once()
            ->with("Processing single image: {$path}");
        
        // Expect error to be logged for file not found
        $this->mockLogger->shouldReceive('error')
            ->once()
            ->with(Mockery::pattern('/Error processing image: File not found.*/'));
            
        // The OpenRouter service should never be called in this case
        $this->mockOpenRouterService->shouldReceive('classifyImage')
            ->never();
            
        // Run the method we're testing
        $result = $this->testableProcessor->processSingle($path);
        
        // Verify result is null on error
        $this->assertNull($result);
    }
    
    public function testProcessSingleHandlesApiException()
    {
        // Test data
        $path = '/path/to/test.jpg';
        $testData = 'test-image-data';
        
        // Configure our testable processor to simulate a file that exists
        $this->testableProcessor->setFileExistsResult(true);
        $this->testableProcessor->setFileContents($testData);
        
        // Logging expectations
        $this->mockLogger->shouldReceive('info')
            ->once()
            ->with("Processing single image: {$path}");
        
        // Setup mock for OpenRouterService to throw an exception
        $this->mockOpenRouterService->shouldReceive('classifyImage')
            ->once()
            ->andThrow(new \Exception('API error'));
        
        // Expect error to be logged for API error
        $this->mockLogger->shouldReceive('error')
            ->once()
            ->with(Mockery::pattern('/Error processing image: API error/'));
            
        // Run the method we're testing
        $result = $this->testableProcessor->processSingle($path);
        
        // Verify result is null on error
        $this->assertNull($result);
    }
}
