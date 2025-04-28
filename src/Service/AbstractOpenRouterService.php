<?php

namespace PhotoDesc\Service;

use Psr\Log\LoggerInterface;

/**
 * Abstract base class for OpenRouter API services
 */
abstract class AbstractOpenRouterService
{
    protected LoggerInterface $logger;
    protected string $apiKey;
    protected string $model;

    /**
     * Constructor for OpenRouter services
     * 
     * @param LoggerInterface $logger Logger instance
     * @param string $apiKey OpenRouter API key
     * @param string $model AI model to use for image analysis
     */
    public function __construct(
        LoggerInterface $logger,
        string $apiKey,
        string $model
    ) {
        $this->logger = $logger;
        $this->apiKey = $apiKey;
        $this->model = $model;
    }

    /**
     * Creates the request body for the OpenRouter API
     * 
     * @param string $base64Image Base64 encoded image
     * @param string $imageName Original image filename
     * @return string JSON request body
     */
    protected function createRequestBody(string $base64Image, string $imageName): string
    {
        return json_encode([
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
    }
    
    /**
     * Process API response to extract metadata
     * 
     * @param string $responseBody JSON response from API
     * @return array Metadata with description and tags
     * @throws \Exception If response cannot be processed
     */
    protected function processResponse(string $responseBody): array
    {
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
    }
    
    /**
     * Get the MIME type for an image based on its file extension
     * 
     * @param string $filename The filename to determine MIME type for
     * @return string The MIME type
     */
    protected function getMimeType(string $filename): string
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
                return 'application/octet-stream';
        }
    }
}
