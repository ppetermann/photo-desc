<?php

namespace PhotoDesc\Config;

/**
 * Application configuration class
 */
class AppConfig
{
    private string $inputFolder;
    private string $outputFolder;
    private string $apiKey;
    private string $logLevel;
    private array $supportedExtensions;
    private string $aiModel;

    /**
     * Constructor
     * 
     * @param string $inputFolder Directory where photos are stored
     * @param string $outputFolder Directory where JSON metadata will be saved
     * @param string $apiKey OpenRouter API key
     * @param string $logLevel Logging level
     * @param array $supportedExtensions List of supported image extensions
     * @param string $aiModel AI model to use for image analysis
     */
    public function __construct(
        string $inputFolder,
        string $outputFolder,
        string $apiKey,
        string $logLevel = 'info',
        array $supportedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        string $aiModel = 'anthropic/claude-3-opus:beta'
    ) {
        $this->inputFolder = $inputFolder;
        $this->outputFolder = $outputFolder;
        $this->apiKey = $apiKey;
        $this->logLevel = $logLevel;
        $this->supportedExtensions = $supportedExtensions;
        $this->aiModel = $aiModel;
    }

    /**
     * Get input folder path
     * 
     * @return string
     */
    public function getInputFolder(): string
    {
        return $this->inputFolder;
    }

    /**
     * Get output folder path
     * 
     * @return string
     */
    public function getOutputFolder(): string
    {
        return $this->outputFolder;
    }

    /**
     * Get API key
     * 
     * @return string
     */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * Get log level
     * 
     * @return string
     */
    public function getLogLevel(): string
    {
        return $this->logLevel;
    }

    /**
     * Get supported extensions
     * 
     * @return array
     */
    public function getSupportedExtensions(): array
    {
        return $this->supportedExtensions;
    }

    /**
     * Get AI model
     * 
     * @return string
     */
    public function getAiModel(): string
    {
        return $this->aiModel;
    }

    /**
     * Validate config
     * 
     * @throws \Exception If API key is empty
     */
    public function validate(): void
    {
        if (empty($this->apiKey)) {
            throw new \Exception("OPENROUTER_API_KEY is not set in configuration");
        }
    }
}
