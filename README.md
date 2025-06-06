# Photo Description Generator (v0.2.1)

This PHP application reads photos from an input folder and uses OpenRouter's AI models to generate tags and descriptions, saving them as JSON metadata files.

## Disclaimer
This project has been created vibe-coding using windsurf with the Claude 3.7 model. While it is functional, it may contain errors or bugs. Use at your own risk.

## Setup

1. Install dependencies:
   ```
   composer install
   ```

2. Copy `.env.example` to `.env` and edit it to add your OpenRouter API key:
   ```
   cp .env.example .env
   nano .env
   ```

3. Place your photos in the `input_photos` directory (it will be created automatically on first run).

## Usage

### Batch Processing

Run the script without arguments to process all photos in the input directory:

```
php process_photos.php
```

The script will:
1. Read all supported image files from the `input_photos` directory
2. Skip already processed images (unless the original image has been modified)
3. Automatically resize images larger than 5MB to meet API limitations
4. Send each image to OpenRouter for analysis using the configured AI model
5. Save the resulting tags and descriptions as JSON files in the `output` directory

### Single Image Processing

You can also process a single image by providing its file path or URL as an argument:

```
# Process a local file
php examples/process_photos.php /path/to/your/image.jpg

# Process an image from URL
php examples/process_photos.php https://example.com/image.jpg

# Process with logging enabled
php examples/process_photos.php --log /path/to/your/image.jpg

# Show help
php examples/process_photos.php --help
```

When processing a single image, the script will output the metadata directly as JSON to stdout, rather than saving it to a file. By default, logging is suppressed in single-image mode to ensure clean JSON output. If you want to see the logs (for debugging), add the `--log` flag.

### Asynchronous Processing with ReactPHP

This package also supports asynchronous processing using ReactPHP. This allows you to process images in a non-blocking way, which is particularly useful for web applications or when processing many images concurrently:

```
# Process a local file asynchronously
php examples/process_photos_async.php /path/to/your/image.jpg

# Process an image from URL asynchronously
php examples/process_photos_async.php https://example.com/image.jpg
```

## Configuration

You can configure the application by editing the `.env` file:

- `OPENROUTER_API_KEY`: Your OpenRouter API key
- `AI_MODEL`: The AI model to use for image analysis (default: `anthropic/claude-3-opus:beta`)
  - Examples: `google/gemini-2.5-pro-preview`, `anthropic/claude-3-sonnet`, `qwen/qwen-2.5-vl-7b-instruct`
  - Any image-capable model supported by OpenRouter can be used
- `INPUT_FOLDER`: Directory where photos are stored (default: `input_photos`)
- `OUTPUT_FOLDER`: Directory where JSON metadata will be saved (default: `output`)
- `LOG_LEVEL`: Logging level, set to `debug` for more verbose output (default: `info`)

## Output Format

Each processed image will generate a JSON file with the following structure:

```json
{
  "description": "A detailed description of the image contents",
  "tags": ["tag1", "tag2", "tag3", "..."]
}
```

## Supported Image Formats

- JPEG (.jpg, .jpeg)
- PNG (.png)
- GIF (.gif)
- WebP (.webp)

## ReactPHP Integration

The package includes full ReactPHP support for asynchronous, non-blocking image processing:

```php
use React\EventLoop\Loop;
use React\Http\Browser;
use PhotoDesc\Service\AsyncOpenRouterService;
use PhotoDesc\AsyncPhotoProcessor;
use Psr\Log\LoggerInterface;

// Create ReactPHP browser
$browser = new Browser(Loop::get());

// Create async services
$asyncService = new AsyncOpenRouterService(
    $browser,
    $logger,  // your PSR-3 logger
    $apiKey,  // your OpenRouter API key
    $model    // the AI model to use
);

$asyncProcessor = new AsyncPhotoProcessor(
    $asyncService,
    $logger
);

// Process an image asynchronously
$asyncProcessor->processSingleAsync('/path/to/image.jpg')
    ->then(
        function ($metadata) {
            // Handle successful result
            echo json_encode($metadata, JSON_PRETTY_PRINT);
        },
        function ($error) {
            // Handle error
            echo "Error: " . $error->getMessage();
        }
    );

// Run the event loop
Loop::get()->run();
```

This approach is ideal for web applications where you don't want to block while waiting for API responses.

## Using as a Composer Package

This project can also be used as a composer package in other PHP applications. The OpenRouterService class is designed to be PSR-compliant and can be easily integrated into other projects.

### Installation

```bash
composer require devedge/photo-desc
```

### Usage in Your Project

#### Synchronous Usage

```php
use PhotoDesc\Service\OpenRouterService;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Psr7\HttpFactory;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Set up dependencies
$client = new Client();
$requestFactory = new HttpFactory();
$streamFactory = new HttpFactory();
$logger = new Logger('photo-description');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

// Your API key and model configuration
$apiKey = 'your-openrouter-api-key';
$model = 'anthropic/claude-3-opus:beta'; // Or any other supported model

// Create the service
$openRouterService = new OpenRouterService(
    $client,
    $requestFactory,
    $streamFactory,
    $logger,
    $apiKey,
    $model
);

// Read an image and convert to base64
$imagePath = 'path/to/your/image.jpg';
$imageData = file_get_contents($imagePath);
$base64Image = base64_encode($imageData);

// Classify the image
$result = $openRouterService->classifyImage($base64Image, basename($imagePath));

if ($result) {
    echo "Description: {$result['description']}\n";
    echo "Tags: " . implode(', ', $result['tags']) . "\n";
}
```

### Dependency Injection

The service uses PSR interfaces rather than concrete implementations, making it easy to integrate with various dependency injection containers:

- `Psr\Http\Client\ClientInterface` - Any PSR-18 HTTP client
- `Psr\Http\Message\RequestFactoryInterface` - Any PSR-17 compatible request factory
- `Psr\Http\Message\StreamFactoryInterface` - Any PSR-17 compatible stream factory
- `Psr\Log\LoggerInterface` - Any PSR-3 logger

