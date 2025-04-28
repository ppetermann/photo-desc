<?php

namespace PhotoDesc\Service;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Synchronous service for interacting with the OpenRouter API
 */
class OpenRouterService extends AbstractOpenRouterService
{
    private ClientInterface $client;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;

    /**
     * Constructor
     * 
     * @param ClientInterface $client HTTP client
     * @param RequestFactoryInterface $requestFactory HTTP request factory
     * @param StreamFactoryInterface $streamFactory HTTP stream factory
     * @param LoggerInterface $logger Logger instance
     * @param string $apiKey OpenRouter API key
     * @param string $model AI model to use for image analysis
     */
    public function __construct(
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        LoggerInterface $logger,
        string $apiKey,
        string $model
    )
    {
        parent::__construct($logger, $apiKey, $model);
        
        $this->client = $client;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
    }

    /**
     * Call OpenRouter API to classify an image
     * 
     * @param string $base64Image Base64 encoded image
     * @param string $imageName Original image filename (used to determine mime type)
     * @return array|null Result array or null on failure
     */
    public function classifyImage(string $base64Image, string $imageName): ?array
    {
        try {
            // Create request body using the shared method
            $requestBody = $this->createRequestBody($base64Image, $imageName);
            
            // Create HTTP stream for the request body
            $bodyStream = $this->streamFactory->createStream($requestBody);
            
            // Create the request
            $request = $this->requestFactory->createRequest('POST', 'https://openrouter.ai/api/v1/chat/completions')
                ->withHeader('Authorization', "Bearer {$this->apiKey}")
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('HTTP-Referer', 'https://localhost')
                ->withHeader('X-Title', 'Photo Description Generator')
                ->withBody($bodyStream);
                
            // Send the request
            $response = $this->client->sendRequest($request);
            
            $responseBody = (string) $response->getBody();
            
            // Process the response using the shared method
            return $this->processResponse($responseBody);
            
        } catch (\Psr\Http\Client\ClientExceptionInterface $e) {
            // Handle HTTP request exceptions using PSR-18 interface
            $this->logger->error('API request error: ' . $e->getMessage());
            
            // Cannot access response the same way with PSR interfaces
            // as different implementations will handle this differently
            
            return null;
        } catch (\Exception $e) {
            // Handle all other exceptions
            $this->logger->error('API processing error: ' . $e->getMessage());
            return null;
        }
    }
}
