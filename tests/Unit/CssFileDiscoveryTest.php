<?php

namespace CustomCssLoader\Tests\Unit;

use PHPUnit\Framework\TestCase;
use CustomCssLoaderPlugin;

/**
 * Tests for CSS file discovery functionality
 */
class CssFileDiscoveryTest extends TestCase
{
    private string $testDir;
    private CustomCssLoaderPlugin $plugin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temporary test directory
        $this->testDir = sys_get_temp_dir() . '/osticket-css-loader-test-' . uniqid();
        mkdir($this->testDir, 0755, true);

        $this->plugin = new CustomCssLoaderPlugin();
        $this->plugin->setTestCssDirectory($this->testDir);
    }

    protected function tearDown(): void
    {
        // Clean up test directory
        $this->removeDirectory($this->testDir);
        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function createTestFile(string $filename, string $content = '/* test */'): void
    {
        file_put_contents($this->testDir . '/' . $filename, $content);
    }

    /**
     * @test
     */
    public function testDiscoversCssFilesInDirectory(): void
    {
        $this->createTestFile('test-staff.css');
        $this->createTestFile('test-client.css');

        $files = $this->plugin->discoverCssFiles();

        $this->assertCount(1, $files['staff']);
        $this->assertCount(1, $files['client']);
    }

    /**
     * @test
     */
    public function testSeparatesStaffAndClientFiles(): void
    {
        $this->createTestFile('admin-staff-theme.css');
        $this->createTestFile('portal-client-custom.css');
        $this->createTestFile('another-staff.css');

        $files = $this->plugin->discoverCssFiles();

        $this->assertCount(2, $files['staff']);
        $this->assertCount(1, $files['client']);

        // Verify filenames
        $staffFilenames = array_column($files['staff'], 'filename');
        $this->assertContains('admin-staff-theme.css', $staffFilenames);
        $this->assertContains('another-staff.css', $staffFilenames);

        $clientFilenames = array_column($files['client'], 'filename');
        $this->assertContains('portal-client-custom.css', $clientFilenames);
    }

    /**
     * @test
     */
    public function testReturnsEmptyArrayForNonexistentDirectory(): void
    {
        $this->plugin->setTestCssDirectory('/nonexistent/path');

        $files = $this->plugin->discoverCssFiles();

        $this->assertEmpty($files['staff']);
        $this->assertEmpty($files['client']);
    }

    /**
     * @test
     */
    public function testIgnoresFilesWithoutPattern(): void
    {
        $this->createTestFile('general-styles.css');
        $this->createTestFile('theme.css');
        $this->createTestFile('staff-overrides.css');

        $files = $this->plugin->discoverCssFiles();

        // Only staff-overrides.css should be found
        $this->assertCount(1, $files['staff']);
        $this->assertEmpty($files['client']);
    }

    /**
     * @test
     */
    public function testCaseInsensitivePatternMatching(): void
    {
        $this->createTestFile('STAFF-uppercase.css');
        $this->createTestFile('Staff-mixed.css');
        $this->createTestFile('CLIENT-uppercase.css');
        $this->createTestFile('Client-mixed.css');

        $files = $this->plugin->discoverCssFiles();

        $this->assertCount(2, $files['staff']);
        $this->assertCount(2, $files['client']);
    }

    /**
     * @test
     */
    public function testIgnoresNonCssFiles(): void
    {
        $this->createTestFile('staff-theme.css');
        $this->createTestFile('staff-notes.txt');
        $this->createTestFile('client-readme.md');

        $files = $this->plugin->discoverCssFiles();

        $this->assertCount(1, $files['staff']);
        $this->assertEmpty($files['client']);
    }

    /**
     * @test
     */
    public function testReturnsFileMtime(): void
    {
        $this->createTestFile('test-staff.css');

        $files = $this->plugin->discoverCssFiles();

        $this->assertArrayHasKey('mtime', $files['staff'][0]);
        $this->assertIsInt($files['staff'][0]['mtime']);
        $this->assertGreaterThan(0, $files['staff'][0]['mtime']);
    }

    /**
     * @test
     */
    public function testReturnsFilePath(): void
    {
        $this->createTestFile('test-staff.css');

        $files = $this->plugin->discoverCssFiles();

        $this->assertArrayHasKey('path', $files['staff'][0]);
        $this->assertStringEndsWith('test-staff.css', $files['staff'][0]['path']);
    }

    /**
     * @test
     */
    public function testHandlesEmptyDirectory(): void
    {
        // testDir is empty by default
        $files = $this->plugin->discoverCssFiles();

        $this->assertEmpty($files['staff']);
        $this->assertEmpty($files['client']);
    }
}
