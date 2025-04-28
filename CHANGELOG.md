# Changelog

All notable changes to the `devedge/photo-desc` package will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.1] - 2025-04-28

### Added
- Pre-tag validation rules in .windsurf.yaml to ensure code quality
- Automated checks for unit tests, skipped tests, and documentation before versioning

### Fixed
- Implemented proper test for URL processing in AsyncPhotoProcessor
- Fixed getMimeType default handling for unknown file types
- Improved README with version information and dependencies

## [0.2.0] - 2025-04-28

### Added
- ReactPHP integration for asynchronous image processing
- AbstractOpenRouterService for better code organization and reduced duplication
- AsyncOpenRouterService for non-blocking API calls
- AsyncPhotoProcessor for event-driven image processing
- Example for asynchronous image processing
- Improved help functionality in example scripts

### Changed
- Moved process_photos.php to examples folder for better organization
- Refactored OpenRouterService to extend from AbstractOpenRouterService
- Added safety rule in .windsurf.yaml to protect .env files

### Fixed
- Better error handling in async implementations
- Improved path handling in moved example scripts

## [0.1.0] - 2025-04-28

### Added
- Initial release with core functionality
- OpenRouter API integration for image classification
- Photo processor for batch processing of images
- Support for both local files and URLs
- Single image processing from command line
- Comprehensive unit testing suite
- Full PSR interface compatibility
- MIT License
