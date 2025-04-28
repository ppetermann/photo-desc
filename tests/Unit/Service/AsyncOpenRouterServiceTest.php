<?php

namespace PhotoDesc\Tests\Unit\Service;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use PhotoDesc\Service\AsyncOpenRouterService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use React\Http\Browser;
use React\Promise\PromiseInterface;

class AsyncOpenRouterServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    
    private $mockBrowser;
    private $mockLogger;
    private $service;
    
    protected function setUp(): void
    {
        $this->mockBrowser = Mockery::mock(Browser::class);
        $this->mockLogger = Mockery::mock(LoggerInterface::class);
        
        $this->service = new AsyncOpenRouterService(
            $this->mockBrowser,
            $this->mockLogger,
            'test-api-key',
            'test-model'
        );
    }
    
    public function testClassifyImageReturnsPromise()
    {
        // Allow debug and info logging
        $this->mockLogger->shouldReceive('info')->zeroOrMoreTimes();
        $this->mockLogger->shouldReceive('debug')->zeroOrMoreTimes();
        
        // Mock the browser post method to return a promise
        $mockPromise = Mockery::mock(PromiseInterface::class);
        $mockPromise->shouldReceive('then')
            ->once()
            ->andReturnSelf();
        
        $this->mockBrowser->shouldReceive('post')
            ->once()
            ->andReturn($mockPromise);
        
        // Call the method we're testing
        $result = $this->service->classifyImage('base64-encoded-image', 'test.jpg');
        
        // Verify that the result is a Promise
        $this->assertInstanceOf(PromiseInterface::class, $result);
    }
    
    public function testClassifyImageHandlesSuccessfulResponse()
    {
        // This test is more complex as it needs to test the Promise chain
        // We'll have to rely on the proper implementation of the ReactPHP promises
        
        // Allow debug and info logging
        $this->mockLogger->shouldReceive('info')->zeroOrMoreTimes();
        $this->mockLogger->shouldReceive('debug')->zeroOrMoreTimes();
        
        // Create a successful response
        $mockResponseBody = json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => '{"description":"Test description","tags":["test","tag"]}'
                    ]
                ]
            ]
        ]);
        
        // Mock the stream for the response body
        $mockStream = Mockery::mock(StreamInterface::class);
        $mockStream->shouldReceive('__toString')
            ->andReturn($mockResponseBody);
        
        // Mock the response
        $mockResponse = Mockery::mock(ResponseInterface::class);
        $mockResponse->shouldReceive('getBody')
            ->andReturn($mockStream);
        
        // Create a promise that resolves with the mock response
        $mockPromise = \React\Promise\resolve($mockResponse);
        
        // Mock the browser to return our promise
        $this->mockBrowser->shouldReceive('post')
            ->once()
            ->andReturn($mockPromise);
        
        // Call the method we're testing
        $resultPromise = $this->service->classifyImage('base64-encoded-image', 'test.jpg');
        
        // Use PHPUnit's assertions in the then callbacks
        $resultPromise->then(
            function ($result) {
                $this->assertIsArray($result);
                $this->assertArrayHasKey('description', $result);
                $this->assertArrayHasKey('tags', $result);
                $this->assertEquals('Test description', $result['description']);
                $this->assertEquals(['test', 'tag'], $result['tags']);
            }
        );
    }
}
