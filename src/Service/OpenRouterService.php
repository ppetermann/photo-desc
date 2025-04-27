<?php

namespace PhotoDesc\Service;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;


/**
 * Service for interacting with the OpenRouter API
 */
class OpenRouterService
{
    private ClientInterface $client;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;
    private LoggerInterface $logger;
    private string $apiKey;
    private string $model;

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
        $this->client = $client;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->logger = $logger;
        $this->apiKey = $apiKey;
        $this->model = $model;
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
            // Create request body as JSON string
            $requestBody = json_encode([
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Please analyze this image and provide a detailed description and relevant tags. Your response MUST be in JSON format with exactly these fields: {"description": "detailed description here", "tags": ["tag1", "tag2", "tag3"]}. Do not include any other text, only the JSON object.'
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:{$this->getMimeType($imageName)};base64,{$base64Image}"
                                ]
                            ]
                        ]
                    ]
                ],
                'max_tokens' => 1500,
                'temperature' => 0.1
            ]);
            
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
            $result = json_decode($responseBody, true);
            
            // Log the raw response for debugging
            $this->logger->debug('Raw API response: ' . $responseBody);
            
            // Validate response structure
            if (!is_array($result)) {
                throw new \Exception("Invalid JSON response from API");
            }
            
            $this->logger->debug('Parsed response: ' . json_encode($result));
            
            // Check if the response contains the expected keys
            if (!isset($result['choices']) || !is_array($result['choices']) || empty($result['choices'])) {
                throw new \Exception("Response doesn't contain 'choices' array");
            }
            
            if (!isset($result['choices'][0]['message']) || !isset($result['choices'][0]['message']['content'])) {
                throw new \Exception("Response doesn't contain expected message content structure");
            }
            
            // Extract the content from the response
            $content = $result['choices'][0]['message']['content'];
            $this->logger->debug('Content from API: ' . $content);
            
            // Try to extract JSON from the content
            $jsonContent = $content;
            if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
                $jsonContent = $matches[1];
                $this->logger->debug('Extracted JSON from markdown code block');
            } elseif (preg_match('/{.*}/s', $content, $matches)) {
                $jsonContent = $matches[0];
                $this->logger->debug('Extracted JSON object from content');
            }
            
            // Clean up and parse the JSON
            $metadata = json_decode(trim($jsonContent), true);
            
            if (!is_array($metadata)) {
                $this->logger->error('Failed to parse JSON content: ' . $jsonContent);
                throw new \Exception("Could not parse JSON from response content");
            }
            
            // Validate the metadata structure
            if (!isset($metadata['description']) || !isset($metadata['tags'])) {
                $this->logger->error('Missing required fields in metadata: ' . json_encode($metadata));
                throw new \Exception("Response doesn't contain required 'description' and 'tags' fields");
            }
            
            return $metadata;
            
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
    
    /**
     * Get the MIME type for an image based on its file extension
     *
     * @param string $filename The filename to determine MIME type for
     * @return string The MIME type
     */
    private function getMimeType(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                return 'image/jpeg';
            case 'png':
                return 'image/png';
            case 'gif':
                return 'image/gif';
            case 'webp':
                return 'image/webp';
            default:
                // Default to jpeg if unknown
                $this->logger->warning("Unknown image extension: {$extension}, defaulting to image/jpeg");
                return 'image/jpeg';
        }
    }
}
