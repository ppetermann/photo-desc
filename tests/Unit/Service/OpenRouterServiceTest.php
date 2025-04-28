<?php

namespace PhotoDesc\Tests\Unit\Service;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use PhotoDesc\Service\OpenRouterService;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;

/**
 * A concrete implementation of ClientExceptionInterface for testing
 */
class TestClientException extends \Exception implements ClientExceptionInterface {}

class OpenRouterServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private $mockClient;
    private $mockRequestFactory;
    private $mockStreamFactory;
    private $mockLogger;
    private $openRouterService;
    private $mockRequest;
    private $mockResponse;
    private $mockStream;

    protected function setUp(): void
    {
        // Create mocks for all PSR interfaces used by OpenRouterService
        $this->mockClient = Mockery::mock(ClientInterface::class);
        $this->mockRequestFactory = Mockery::mock(RequestFactoryInterface::class);
        $this->mockStreamFactory = Mockery::mock(StreamFactoryInterface::class);
        $this->mockLogger = Mockery::mock(LoggerInterface::class);
        
        // Common mocks needed for most tests
        $this->mockRequest = Mockery::mock(RequestInterface::class);
        $this->mockResponse = Mockery::mock(ResponseInterface::class);
        $this->mockStream = Mockery::mock(StreamInterface::class);
        
        // This demonstrates our commitment to proper dependency injection
        // by using interfaces instead of concrete implementations
        $this->openRouterService = new OpenRouterService(
            $this->mockClient,
            $this->mockRequestFactory,
            $this->mockStreamFactory,
            $this->mockLogger,
            'test-api-key',
            'test-model'
        );
    }

    public function testClassifyImageSuccessful()
    {
        // Sample successful response from the API
        $successResponse = json_encode([
            'id' => 'test-id',
            'choices' => [
                [
                    'message' => [
                        'content' => '```json
{
    "description": "A test image description",
    "tags": ["test", "image", "tags"]
}
```'
                    ]
                ]
            ]
        ]);
        
        // Configure mocks with expected behavior
        $this->mockStreamFactory->shouldReceive('createStream')
            ->once()
            ->andReturn($this->mockStream);
        
        $this->mockRequestFactory->shouldReceive('createRequest')
            ->once()
            ->with('POST', 'https://openrouter.ai/api/v1/chat/completions')
            ->andReturn($this->mockRequest);
        
        $this->mockRequest->shouldReceive('withHeader')
            ->times(4)
            ->andReturn($this->mockRequest);
        
        $this->mockRequest->shouldReceive('withBody')
            ->once()
            ->with($this->mockStream)
            ->andReturn($this->mockRequest);
        
        $this->mockClient->shouldReceive('sendRequest')
            ->once()
            ->with($this->mockRequest)
            ->andReturn($this->mockResponse);
        
        $this->mockResponse->shouldReceive('getBody')
            ->once()
            ->andReturn($this->mockStream);
        
        $this->mockStream->shouldReceive('__toString')
            ->once()
            ->andReturn($successResponse);
        
        // Allow any debug logging
        $this->mockLogger->shouldReceive('debug')
            ->zeroOrMoreTimes();
        
        // Test the method
        $result = $this->openRouterService->classifyImage('base64imagedata', 'test.jpg');
        
        // Verify result structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('description', $result);
        $this->assertArrayHasKey('tags', $result);
        $this->assertEquals('A test image description', $result['description']);
        $this->assertEquals(['test', 'image', 'tags'], $result['tags']);
    }

    public function testClassifyImageReturnsNullOnRequestException()
    {
        // Configure mocks to throw an exception
        $this->mockStreamFactory->shouldReceive('createStream')
            ->once()
            ->andReturn($this->mockStream);
        
        $this->mockRequestFactory->shouldReceive('createRequest')
            ->once()
            ->andReturn($this->mockRequest);
        
        $this->mockRequest->shouldReceive('withHeader')
            ->times(4)
            ->andReturn($this->mockRequest);
        
        $this->mockRequest->shouldReceive('withBody')
            ->once()
            ->andReturn($this->mockRequest);
        
        $this->mockClient->shouldReceive('sendRequest')
            ->once()
            ->andThrow(new TestClientException('Network error'));
        
        // Expect error to be logged
        $this->mockLogger->shouldReceive('error')
            ->once()
            ->with(Mockery::pattern('/API request error: .*/'));
        
        // Test the method
        $result = $this->openRouterService->classifyImage('base64imagedata', 'test.jpg');
        
        // Assert it gracefully returns null on error
        $this->assertNull($result);
    }

    public function testGetMimeTypeReturnsCorrectType()
    {
        // Allow warning logs for unknown extensions
        $this->mockLogger->shouldReceive('warning')
            ->zeroOrMoreTimes()
            ->with(Mockery::pattern('/Unknown image extension:.*/'));
            
        // Test JPEG detection
        $result = $this->callPrivateMethod($this->openRouterService, 'getMimeType', ['image.jpg']);
        $this->assertEquals('image/jpeg', $result);
        
        // Test PNG detection 
        $result = $this->callPrivateMethod($this->openRouterService, 'getMimeType', ['image.png']);
        $this->assertEquals('image/png', $result);
        
        // Test for unknown extension
        $result = $this->callPrivateMethod($this->openRouterService, 'getMimeType', ['image.unknown']);
        $this->assertEquals('image/jpeg', $result); // Default is jpeg
        
        // Test GIF detection
        $result = $this->callPrivateMethod($this->openRouterService, 'getMimeType', ['image.gif']);
        $this->assertEquals('image/gif', $result);
        
        // Test WebP detection
        $result = $this->callPrivateMethod($this->openRouterService, 'getMimeType', ['image.webp']);
        $this->assertEquals('image/webp', $result);
    }
    
    /**
     * Helper to call private methods for testing
     */
    private function callPrivateMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        
        return $method->invokeArgs($object, $parameters);
    }
}
