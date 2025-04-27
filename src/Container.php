<?php

namespace PhotoDesc;

use GuzzleHttp\Client;
use King23\DI\DependencyContainer;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PhotoDesc\Config\AppConfig;
use PhotoDesc\Service\FileSystemService;
use PhotoDesc\Service\OpenRouterService;

/**
 * Application container setup
 */
class Container
{
    /**
     * Create and configure the dependency injection container
     * 
     * @return DependencyContainer
     */
    public static function create(): DependencyContainer
    {
        $container = new DependencyContainer();
        
        // Register configuration
        $container->register(AppConfig::class, function () {
            $inputFolder = $_ENV['INPUT_FOLDER'] ?? 'input_photos';
            $outputFolder = $_ENV['OUTPUT_FOLDER'] ?? 'output';
            $apiKey = $_ENV['OPENROUTER_API_KEY'] ?? '';
            $logLevel = $_ENV['LOG_LEVEL'] ?? 'info';
            $aiModel = $_ENV['AI_MODEL'] ?? 'anthropic/claude-3-opus:beta';
            
            $config = new AppConfig(
                $inputFolder,
                $outputFolder,
                $apiKey,
                $logLevel,
                ['jpg', 'jpeg', 'png', 'gif', 'webp'],
                $aiModel
            );
            
            $config->validate();
            
            return $config;
        });
        
        // Register logger
        $container->register(Logger::class, function () use ($container) {
            /** @var AppConfig $config */
            $config = $container->get(AppConfig::class);
            
            $logger = new Logger('photo-description');
            $logLevel = $config->getLogLevel() === 'debug' ? Logger::DEBUG : Logger::INFO;
            $logHandler = new StreamHandler('php://stdout', $logLevel);
            
            $logger->pushHandler($logHandler);
            
            return $logger;
        });
        
        // Register HTTP client
        $container->register(Client::class, function () {
            return new Client();
        });
        
        // Register services
        $container->register(FileSystemService::class, function () use ($container) {
            /** @var AppConfig $config */
            $config = $container->get(AppConfig::class);
            
            return new FileSystemService(
                $container->get(Logger::class),
                $config->getInputFolder(),
                $config->getOutputFolder(),
                $config->getSupportedExtensions()
            );
        });
        
        $container->register(OpenRouterService::class, function () use ($container) {
            /** @var AppConfig $config */
            $config = $container->get(AppConfig::class);
            
            return new OpenRouterService(
                $container->get(Client::class),
                $container->get(Logger::class),
                $config->getApiKey(),
                $config->getAiModel()
            );
        });
        
        // Register main application
        $container->register(PhotoProcessor::class, function () use ($container) {
            return new PhotoProcessor(
                $container->get(FileSystemService::class),
                $container->get(OpenRouterService::class),
                $container->get(Logger::class)
            );
        });
        
        return $container;
    }
}
