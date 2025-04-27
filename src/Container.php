<?php

namespace PhotoDesc;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use King23\DI\DependencyContainer;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PhotoDesc\Config\AppConfig;
use PhotoDesc\Service\FileSystemService;
use PhotoDesc\Service\OpenRouterService;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Application container setup
 */
class Container
{
    /**
     * Create and configure the dependency injection container
     * 
     * @param bool $quietMode If true, logging will be suppressed (useful for clean JSON output)
     * @return DependencyContainer
     */
    public static function create(bool $quietMode = false): DependencyContainer
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
        $container->register(Logger::class, function () use ($container, $quietMode) {
            /** @var AppConfig $config */
            $config = $container->get(AppConfig::class);
            
            $logger = new Logger('photo-description');
            
            if ($quietMode) {
                // In quiet mode, use NullHandler to suppress all logging output
                $logger->pushHandler(new NullHandler());
            } else {
                // Normal logging to stdout
                $logLevel = $config->getLogLevel() === 'debug' ? Logger::DEBUG : Logger::INFO;
                $logHandler = new StreamHandler('php://stdout', $logLevel);
                $logger->pushHandler($logHandler);
            }
            
            return $logger;
        });
        $container->register(LoggerInterface::class, function () use ($container) {
            return $container->get(Logger::class);
        });
        
        // Register HTTP client and factories
        $container->register(Client::class, function () {
            return new Client();
        });
        $container->register(ClientInterface::class, function () use ($container) {
            return $container->get(Client::class);
        });
        
        $container->register(HttpFactory::class, function () {
            return new HttpFactory();
        });
        $container->register(RequestFactoryInterface::class, function () use ($container) {
            return $container->get(HttpFactory::class);
        });
        $container->register(StreamFactoryInterface::class, function () use ($container) {
            return $container->get(HttpFactory::class);
        });
        
        // Register services
        $container->register(FileSystemService::class, function () use ($container) {
            /** @var AppConfig $config */
            $config = $container->get(AppConfig::class);
            
            return new FileSystemService(
                $container->get(LoggerInterface::class),
                $config->getInputFolder(),
                $config->getOutputFolder(),
                $config->getSupportedExtensions()
            );
        });
        
        $container->register(OpenRouterService::class, function () use ($container) {
            /** @var AppConfig $config */
            $config = $container->get(AppConfig::class);
            
            return new OpenRouterService(
                $container->get(ClientInterface::class),
                $container->get(RequestFactoryInterface::class),
                $container->get(StreamFactoryInterface::class),
                $container->get(LoggerInterface::class),
                $config->getApiKey(),
                $config->getAiModel()
            );
        });
        
        // Register main application
        $container->register(PhotoProcessor::class, function () use ($container) {
            return new PhotoProcessor(
                $container->get(FileSystemService::class),
                $container->get(OpenRouterService::class),
                $container->get(LoggerInterface::class)
            );
        });
        
        return $container;
    }
}
