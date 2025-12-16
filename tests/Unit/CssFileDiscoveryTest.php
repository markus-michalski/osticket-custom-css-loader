<?php

declare(strict_types=1);

namespace CustomCssLoader\Tests\Unit;

use CustomCssLoader\Discovery\FilesystemCssDiscovery;
use CustomCssLoaderPlugin;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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

        // Clear static state before each test
        CustomCssLoaderPlugin::clearStaticState();
    }

    protected function tearDown(): void
    {
        // Clean up test directory
        $this->removeDirectory($this->testDir);

        // Clear static state after each test
        CustomCssLoaderPlugin::clearStaticState();

        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
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

    #[Test]
    public function discoversCssFilesInDirectory(): void
    {
        $this->createTestFile('test-staff.css');
        $this->createTestFile('test-client.css');

        $files = $this->plugin->discoverCssFiles();

        $this->assertCount(1, $files['staff']);
        $this->assertCount(1, $files['client']);
    }

    #[Test]
    public function separatesStaffAndClientFiles(): void
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

    #[Test]
    public function returnsEmptyArrayForNonexistentDirectory(): void
    {
        $this->plugin->setTestCssDirectory('/nonexistent/path');

        $files = $this->plugin->discoverCssFiles();

        $this->assertEmpty($files['staff']);
        $this->assertEmpty($files['client']);
    }

    #[Test]
    public function ignoresFilesWithoutPattern(): void
    {
        $this->createTestFile('general-styles.css');
        $this->createTestFile('theme.css');
        $this->createTestFile('staff-overrides.css');

        $files = $this->plugin->discoverCssFiles();

        // Only staff-overrides.css should be found
        $this->assertCount(1, $files['staff']);
        $this->assertEmpty($files['client']);
    }

    #[Test]
    public function caseInsensitivePatternMatching(): void
    {
        $this->createTestFile('STAFF-uppercase.css');
        $this->createTestFile('Staff-mixed.css');
        $this->createTestFile('CLIENT-uppercase.css');
        $this->createTestFile('Client-mixed.css');

        $files = $this->plugin->discoverCssFiles();

        $this->assertCount(2, $files['staff']);
        $this->assertCount(2, $files['client']);
    }

    #[Test]
    public function ignoresNonCssFiles(): void
    {
        $this->createTestFile('staff-theme.css');
        $this->createTestFile('staff-notes.txt');
        $this->createTestFile('client-readme.md');

        $files = $this->plugin->discoverCssFiles();

        $this->assertCount(1, $files['staff']);
        $this->assertEmpty($files['client']);
    }

    #[Test]
    public function returnsFileMtime(): void
    {
        $this->createTestFile('test-staff.css');

        $files = $this->plugin->discoverCssFiles();

        $this->assertArrayHasKey('mtime', $files['staff'][0]);
        $this->assertIsInt($files['staff'][0]['mtime']);
        $this->assertGreaterThan(0, $files['staff'][0]['mtime']);
    }

    #[Test]
    public function returnsFilePath(): void
    {
        $this->createTestFile('test-staff.css');

        $files = $this->plugin->discoverCssFiles();

        $this->assertArrayHasKey('path', $files['staff'][0]);
        $this->assertStringEndsWith('test-staff.css', $files['staff'][0]['path']);
    }

    #[Test]
    public function handlesEmptyDirectory(): void
    {
        // testDir is empty by default
        $files = $this->plugin->discoverCssFiles();

        $this->assertEmpty($files['staff']);
        $this->assertEmpty($files['client']);
    }

    #[Test]
    public function discoveryServiceCanBeInjected(): void
    {
        // Create a custom discovery with custom patterns
        $customDiscovery = new FilesystemCssDiscovery(
            $this->testDir,
            ['admin' => '/admin/i', 'public' => '/public/i']
        );

        $this->createTestFile('admin-theme.css');
        $this->createTestFile('public-portal.css');

        // Use discoverFilesRaw() for custom patterns beyond staff/client
        $files = $customDiscovery->discoverFilesRaw();

        $this->assertCount(1, $files['admin']);
        $this->assertCount(1, $files['public']);
    }
}
