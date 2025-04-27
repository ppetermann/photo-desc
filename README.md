# Photo Description Generator

This PHP application reads photos from an input folder and uses OpenRouter's AI models to generate tags and descriptions, saving them as JSON metadata files.

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

Run the script to process all photos:

```
php process_photos.php
```

The script will:
1. Read all supported image files from the `input_photos` directory
2. Skip already processed images (unless the original image has been modified)
3. Automatically resize images larger than 5MB to meet API limitations
4. Send each image to OpenRouter for analysis using the configured AI model
5. Save the resulting tags and descriptions as JSON files in the `output` directory

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

## Using as a Composer Package

This project can also be used as a composer package in other PHP applications. The OpenRouterService class is designed to be PSR-compliant and can be easily integrated into other projects.

### Installation

```bash
composer require ppetermann/photo-desc
```

### Usage in Your Project

```php
use PhotoDesc\Service\OpenRouterService;
use GuzzleHttp\Client;
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

