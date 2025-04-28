<?php

namespace PhotoDesc\Tests\Unit;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use PhotoDesc\AsyncPhotoProcessor;
use PhotoDesc\Service\AsyncOpenRouterService;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;

/**
 * Test subclass that overrides file operations for testing
 */
class TestableAsyncPhotoProcessor extends AsyncPhotoProcessor
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

class AsyncPhotoProcessorTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    
    private $mockOpenRouterService;
    private $mockLogger;
    private $processor;
    private $testableProcessor;
    
    protected function setUp(): void
    {
        $this->mockOpenRouterService = Mockery::mock(AsyncOpenRouterService::class);
        $this->mockLogger = Mockery::mock(LoggerInterface::class);
        
        $this->processor = new AsyncPhotoProcessor(
            $this->mockOpenRouterService,
            $this->mockLogger
        );
    }
    
    public function testProcessSingleAsyncReturnsPromise()
    {
        // Mock file operations - we'd use TestableAsyncPhotoProcessor in a more complete test
        
        // Allow all logging
        $this->mockLogger->shouldReceive('info')->zeroOrMoreTimes();
        $this->mockLogger->shouldReceive('error')->zeroOrMoreTimes();
        
        // Mock the service to return a promise
        $mockPromise = Mockery::mock(PromiseInterface::class);
        $mockPromise->shouldReceive('otherwise')
            ->andReturnSelf();
            
        $this->mockOpenRouterService->shouldReceive('classifyImage')
            ->once()
            ->andReturn($mockPromise);
        
        // We need to use a workaround for testing since we can't mock PHP functions easily
        $path = __FILE__; // Use this test file as an existing file to process
        
        // Call the method we're testing
        $result = $this->processor->processSingleAsync($path);
        
        // Verify the result is a Promise
        $this->assertInstanceOf(PromiseInterface::class, $result);
    }
    
    public function testProcessSingleAsyncWithUrl()
    {
        // Allow all logging
        $this->mockLogger->shouldReceive('info')->zeroOrMoreTimes();
        $this->mockLogger->shouldReceive('error')->zeroOrMoreTimes();
        
        // This test is more limited because it's difficult to mock ReactPHP HTTP requests
        // In a more comprehensive test suite, we would use proper dependency injection
        // to make HTTP operations mockable
        
        // Skip actually running this test as it would require more complex setup
        $this->markTestSkipped(
            'This test would require more extensive mocking of ReactPHP HTTP components'
        );
        
        $url = 'https://example.com/image.jpg';
        
        // Call the method we're testing
        $result = $this->processor->processSingleAsync($url);
        
        // Verify the result is a Promise
        $this->assertInstanceOf(PromiseInterface::class, $result);
    }
}
