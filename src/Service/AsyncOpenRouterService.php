<?php

namespace PhotoDesc\Service;

use Psr\Log\LoggerInterface;
use React\Http\Browser;
use React\Promise\PromiseInterface;

/**
 * Async version of OpenRouterService for use with ReactPHP
 */
class AsyncOpenRouterService extends AbstractOpenRouterService
{
    private Browser $browser;

    /**
     * Constructor
     * 
     * @param Browser $browser ReactPHP HTTP browser
     * @param LoggerInterface $logger Logger instance
     * @param string $apiKey OpenRouter API key
     * @param string $model AI model to use for image analysis
     */
    public function __construct(
        Browser $browser,
        LoggerInterface $logger,
        string $apiKey,
        string $model
    ) {
        parent::__construct($logger, $apiKey, $model);
        $this->browser = $browser;
    }

    /**
     * Call OpenRouter API to classify an image asynchronously
     * 
     * @param string $base64Image Base64 encoded image
     * @param string $imageName Original image filename (used to determine mime type)
     * @return PromiseInterface Promise resolving to array metadata or rejecting with an exception
     */
    public function classifyImage(string $base64Image, string $imageName): PromiseInterface
    {
        $this->logger->info("Async processing of image: {$imageName}");
        
        // Create request body using the shared method
        $requestBody = $this->createRequestBody($base64Image, $imageName);
        
        // Debug the request parameters
        $this->logger->debug('AsyncOpenRouterService: Request to OpenRouter with model: ' . $this->model);
        $this->logger->debug('AsyncOpenRouterService: API key starts with: ' . substr($this->apiKey, 0, 10) . '...');
        
        // Pass headers directly like in our successful simplified example
        $headers = [
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
            'HTTP-Referer' => 'https://localhost',
            'X-Title' => 'Photo Description Generator'
        ];
        
        $this->logger->debug('Using headers: ' . json_encode(array_keys($headers)));
        
        // Send the request asynchronously with headers passed directly
        return $this->browser->post(
            'https://openrouter.ai/api/v1/chat/completions',
            $headers,
            $requestBody
        )->then(
            function (\Psr\Http\Message\ResponseInterface $response) {
                $responseBody = (string) $response->getBody();
                
                // Process the response using the shared method
                return $this->processResponse($responseBody);
            },
            function (\Exception $e) {
                // Handle errors
                $this->logger->error("Error in API request: {$e->getMessage()}");
                throw $e;
            }
        );
    }
    
    // The getMimeType method is now provided by the abstract parent class
}
