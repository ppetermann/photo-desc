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
