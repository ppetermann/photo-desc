<?php

namespace PhotoDesc\Tests\Unit\Service;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use PhotoDesc\Service\FileSystemService;
use Psr\Log\LoggerInterface;

class FileSystemServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private $mockLogger;
    private $service;
    private $inputFolder;
    private $outputFolder;
    private $supportedExtensions;

    protected function setUp(): void
    {
        $this->mockLogger = Mockery::mock(LoggerInterface::class);
        $this->inputFolder = 'tests/fixtures/input';
        $this->outputFolder = 'tests/fixtures/output';
        $this->supportedExtensions = ['jpg', 'png', 'gif', 'webp'];
        
        // Create the service with mocked dependencies and test configuration
        $this->service = new FileSystemService(
            $this->mockLogger,
            $this->inputFolder,
            $this->outputFolder,
            $this->supportedExtensions
        );
        
        // Ensure test directories exist
        if (!is_dir($this->inputFolder)) {
            mkdir($this->inputFolder, 0777, true);
        }
        
        if (!is_dir($this->outputFolder)) {
            mkdir($this->outputFolder, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up any test files
        $this->removeTestFiles($this->inputFolder);
        $this->removeTestFiles($this->outputFolder);
    }
    
    private function removeTestFiles($directory)
    {
        $files = glob($directory . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testInitializeFolders()
    {
        // Remove test directories for this test
        if (is_dir($this->inputFolder)) {
            rmdir($this->inputFolder);
        }
        
        if (is_dir($this->outputFolder)) {
            rmdir($this->outputFolder);
        }
        
        // Allow all types of logging
        $this->mockLogger->shouldReceive('debug')->zeroOrMoreTimes();
        $this->mockLogger->shouldReceive('info')->zeroOrMoreTimes();
        $this->mockLogger->shouldReceive('warning')->zeroOrMoreTimes();
        $this->mockLogger->shouldReceive('error')->zeroOrMoreTimes();
        
        // Test the method
        $this->service->initializeFolders();
        
        // Verify both directories exist
        $this->assertTrue(is_dir($this->inputFolder), 'Input folder should be created');
        $this->assertTrue(is_dir($this->outputFolder), 'Output folder should be created');
    }

    public function testGetImagesList()
    {
        // Create test image files
        touch($this->inputFolder . '/test1.jpg');
        touch($this->inputFolder . '/test2.png');
        touch($this->inputFolder . '/invalid.txt');
        
        // Allow all types of logging
        $this->mockLogger->shouldReceive('debug')->zeroOrMoreTimes();
        $this->mockLogger->shouldReceive('info')->zeroOrMoreTimes();
        $this->mockLogger->shouldReceive('warning')->zeroOrMoreTimes();
        $this->mockLogger->shouldReceive('error')->zeroOrMoreTimes();
        
        // Test the method
        $results = $this->service->getImagesList();
        
        // Verify results
        $this->assertCount(2, $results);
        $this->assertContains('test1.jpg', $results);
        $this->assertContains('test2.png', $results);
        $this->assertNotContains('invalid.txt', $results);
    }

    public function testIsImageProcessedReturnsTrueWhenNewerMetadataExists()
    {
        $imageName = 'test.jpg';
        $metadataPath = $this->outputFolder . '/' . pathinfo($imageName, PATHINFO_FILENAME) . '.json';
        
        // Create test image and metadata files
        touch($this->inputFolder . '/' . $imageName, time() - 100); // Older file
        file_put_contents($metadataPath, '{}'); // Newer metadata file
        
        // Allow all types of logging
        $this->mockLogger->shouldReceive('debug')->zeroOrMoreTimes();
        $this->mockLogger->shouldReceive('info')->zeroOrMoreTimes();
        $this->mockLogger->shouldReceive('warning')->zeroOrMoreTimes();
        $this->mockLogger->shouldReceive('error')->zeroOrMoreTimes();
        
        // Test the method
        $result = $this->service->isImageProcessed($imageName);
        
        // Verify result
        $this->assertTrue($result, 'Should return true when metadata exists and is newer');
    }

    public function testSaveMetadata()
    {
        $imageName = 'save_test.jpg';
        $testData = [
            'description' => 'Test description',
            'tags' => ['test', 'tag']
        ];
        
        // Allow all types of logging
        $this->mockLogger->shouldReceive('debug')->zeroOrMoreTimes();
        $this->mockLogger->shouldReceive('info')->zeroOrMoreTimes();
        $this->mockLogger->shouldReceive('warning')->zeroOrMoreTimes();
        $this->mockLogger->shouldReceive('error')->zeroOrMoreTimes();
        
        // Test the method
        $this->service->saveMetadata($imageName, $testData);
        
        // Verify the file was created
        $metadataPath = $this->outputFolder . '/' . pathinfo($imageName, PATHINFO_FILENAME) . '.json';
        $this->assertFileExists($metadataPath, 'Metadata file should be created');
        
        // Verify the contents
        $savedData = json_decode(file_get_contents($metadataPath), true);
        $this->assertEquals($testData, $savedData, 'Saved metadata should match input data');
    }
}
