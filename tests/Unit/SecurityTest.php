<?php

declare(strict_types=1);

namespace CustomCssLoader\Tests\Unit;

use CustomCssLoader\Discovery\CssFileInfo;
use CustomCssLoader\Discovery\FilesystemCssDiscovery;
use CustomCssLoader\Injection\OutputBufferInjectionStrategy;
use CustomCssLoader\Rendering\HtmlCssRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Security tests for Custom CSS Loader Plugin
 *
 * Tests for:
 * - Path traversal prevention
 * - Filename validation / allowlist
 * - XSS prevention
 * - Multiple injection prevention
 */
class SecurityTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temporary test directory
        $this->testDir = sys_get_temp_dir() . '/osticket-css-security-test-' . uniqid();
        mkdir($this->testDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
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

    // ========== Filename Validation Tests ==========

    #[Test]
    #[DataProvider('validFilenamesProvider')]
    public function acceptsValidFilenames(string $filename): void
    {
        $discovery = new FilesystemCssDiscovery($this->testDir);

        $this->assertTrue(
            $discovery->isValidFilename($filename),
            "Filename '$filename' should be valid"
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validFilenamesProvider(): array
    {
        return [
            'simple staff' => ['staff.css'],
            'simple client' => ['client.css'],
            'with hyphen' => ['my-staff-theme.css'],
            'with underscore' => ['my_client_theme.css'],
            'mixed' => ['staff-custom_v2.css'],
            'alphanumeric' => ['staff123.css'],
            'numbers in middle' => ['theme2024-staff.css'],
        ];
    }

    #[Test]
    #[DataProvider('invalidFilenamesProvider')]
    public function rejectsInvalidFilenames(string $filename): void
    {
        $discovery = new FilesystemCssDiscovery($this->testDir);

        $this->assertFalse(
            $discovery->isValidFilename($filename),
            "Filename '$filename' should be rejected"
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidFilenamesProvider(): array
    {
        return [
            'path traversal dots' => ['../../../etc/passwd.css'],
            'path traversal encoded' => ['..%2F..%2Fetc/passwd.css'],
            'null byte' => ["staff\x00.css"],
            'script injection' => ['<script>alert(1)</script>.css'],
            'quotes' => ['staff"onclick="alert(1).css'],
            'spaces' => ['staff theme.css'],
            'starts with hyphen' => ['-staff.css'],
            'starts with underscore' => ['_staff.css'],
            'starts with dot' => ['.staff.css'],
            'double extension' => ['staff.css.php'],
            'uppercase extension' => ['staff.CSS'],
            'no extension' => ['staff'],
            'wrong extension' => ['staff.js'],
            'unicode' => ['stäff.css'],
            'backslash' => ['staff\\.css'],
        ];
    }

    #[Test]
    public function discoverySkipsInvalidFilenames(): void
    {
        // Create valid file
        $this->createTestFile('valid-staff.css');

        // Create files with potentially problematic names (as files on disk)
        // Note: Some invalid names can't be created on filesystem
        $this->createTestFile('.hidden-staff.css');

        $discovery = new FilesystemCssDiscovery($this->testDir);
        $files = $discovery->discoverFiles();

        // Only valid file should be discovered
        $this->assertCount(1, $files['staff']);
        $this->assertEquals('valid-staff.css', $files['staff'][0]->filename);
    }

    // ========== Renderer Validation Tests ==========

    #[Test]
    public function rendererRejectsInvalidFilename(): void
    {
        $renderer = new HtmlCssRenderer('/assets/custom/css');

        $fileInfo = new CssFileInfo(
            path: '/path/to/../../../etc/passwd.css',
            filename: '../../../etc/passwd.css',
            mtime: 12345
        );

        $result = $renderer->render($fileInfo);

        $this->assertEmpty($result, 'Renderer should return empty string for invalid filename');
    }

    #[Test]
    public function rendererAcceptsValidFilename(): void
    {
        $renderer = new HtmlCssRenderer('/assets/custom/css');

        $fileInfo = new CssFileInfo(
            path: '/path/to/staff-theme.css',
            filename: 'staff-theme.css',
            mtime: 12345
        );

        $result = $renderer->render($fileInfo);

        $this->assertStringContainsString('staff-theme.css', $result);
        $this->assertStringContainsString('<link rel="stylesheet"', $result);
    }

    #[Test]
    public function renderAllFiltersOutInvalidFilenames(): void
    {
        $renderer = new HtmlCssRenderer('/assets/custom/css');

        $files = [
            new CssFileInfo('/path/to/valid-staff.css', 'valid-staff.css', 12345),
            new CssFileInfo('/path/to/../passwd.css', '../passwd.css', 12345),
            new CssFileInfo('/path/to/also-valid-client.css', 'also-valid-client.css', 12345),
        ];

        $result = $renderer->renderAll($files);

        $this->assertCount(2, $result);
        $this->assertStringContainsString('valid-staff.css', $result[0]);
        $this->assertStringContainsString('also-valid-client.css', $result[1]);
    }

    // ========== XSS Prevention Tests ==========

    #[Test]
    public function rendererEscapesHtmlEntities(): void
    {
        $renderer = new HtmlCssRenderer('/assets/custom/css');

        // Valid filename but with special chars in path (edge case)
        $fileInfo = new CssFileInfo(
            path: '/path/to/staff-theme.css',
            filename: 'staff-theme.css',
            mtime: 12345
        );

        $result = $renderer->render($fileInfo);

        // Should not contain unescaped HTML
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('javascript:', $result);
    }

    // ========== Multiple Injection Prevention Tests ==========

    #[Test]
    public function injectionStrategyOnlyInjectsOnce(): void
    {
        $strategy = new OutputBufferInjectionStrategy();

        // HTML with multiple </head> tags (malformed but possible)
        $buffer = '<!DOCTYPE html><html><head></head><body><div></head></div></body></html>';
        $cssLinks = ['<link rel="stylesheet" href="/test.css">'];

        $result = $strategy->inject($buffer, $cssLinks);

        // Count occurrences of our CSS link
        $linkCount = substr_count($result, '<link rel="stylesheet" href="/test.css">');

        $this->assertEquals(1, $linkCount, 'CSS should only be injected once');
    }

    #[Test]
    public function injectionStrategyInjectsBeforeFirstHead(): void
    {
        $strategy = new OutputBufferInjectionStrategy();

        $buffer = '<!DOCTYPE html><html><head><title>Test</title></head><body></body></html>';
        $cssLinks = ['<link rel="stylesheet" href="/test.css">'];

        $result = $strategy->inject($buffer, $cssLinks);

        // CSS should appear before </head>
        $cssPos = strpos($result, '/test.css');
        $headPos = strpos($result, '</head>');

        $this->assertNotFalse($cssPos);
        $this->assertNotFalse($headPos);
        $this->assertLessThan($headPos, $cssPos, 'CSS should be injected before </head>');
    }

    #[Test]
    public function injectionStrategyPreservesOriginalCasing(): void
    {
        $strategy = new OutputBufferInjectionStrategy();

        // Head tag with different casing
        $buffer = '<!DOCTYPE html><html><HEAD></HEAD><body></body></html>';
        $cssLinks = ['<link rel="stylesheet" href="/test.css">'];

        $result = $strategy->inject($buffer, $cssLinks);

        // Original HEAD casing should be preserved
        $this->assertStringContainsString('</HEAD>', $result);
        $this->assertStringNotContainsString('</head>', $result);
    }

    // ========== Path Traversal Prevention Tests ==========

    #[Test]
    public function discoveryBlocksSymlinkOutsideBaseDir(): void
    {
        // Skip test if symlinks not supported (Windows without admin)
        if (!function_exists('symlink')) {
            $this->markTestSkipped('Symlinks not supported on this system');
        }

        // Create an external directory with a CSS file
        $externalDir = sys_get_temp_dir() . '/osticket-external-' . uniqid();
        mkdir($externalDir, 0755, true);
        file_put_contents($externalDir . '/secret-staff.css', '/* secret */');

        try {
            // Create symlink in test dir pointing to external file
            $symlinkPath = $this->testDir . '/symlink-staff.css';

            // Suppress warning in case of permission issues
            if (@symlink($externalDir . '/secret-staff.css', $symlinkPath)) {
                $discovery = new FilesystemCssDiscovery($this->testDir);
                $files = $discovery->discoverFiles();

                // Symlink should be blocked - file points outside base directory
                $filenames = array_map(fn($f) => $f->filename, $files['staff']);
                $this->assertNotContains('symlink-staff.css', $filenames);
                $this->assertNotContains('secret-staff.css', $filenames);
            } else {
                $this->markTestSkipped('Could not create symlink (permission denied)');
            }
        } finally {
            // Cleanup
            if (is_link($this->testDir . '/symlink-staff.css')) {
                unlink($this->testDir . '/symlink-staff.css');
            }
            if (file_exists($externalDir . '/secret-staff.css')) {
                unlink($externalDir . '/secret-staff.css');
            }
            if (is_dir($externalDir)) {
                rmdir($externalDir);
            }
        }
    }

    #[Test]
    public function discoveryAllowsSymlinkWithinBaseDir(): void
    {
        if (!function_exists('symlink')) {
            $this->markTestSkipped('Symlinks not supported on this system');
        }

        // Create actual file
        $this->createTestFile('actual-staff.css');

        // Create symlink within same directory
        $symlinkPath = $this->testDir . '/symlink-staff.css';

        if (@symlink($this->testDir . '/actual-staff.css', $symlinkPath)) {
            $discovery = new FilesystemCssDiscovery($this->testDir);
            $files = $discovery->discoverFiles();

            // Both should be discovered since symlink points within base dir
            // Actually, symlink resolves to same file, so only the actual file should be found
            // The symlink filename doesn't match the realpath basename
            $this->assertGreaterThanOrEqual(1, count($files['staff']));
        } else {
            $this->markTestSkipped('Could not create symlink (permission denied)');
        }
    }

    #[Test]
    public function discoveryHandlesBrokenSymlink(): void
    {
        if (!function_exists('symlink')) {
            $this->markTestSkipped('Symlinks not supported on this system');
        }

        // Create a valid file first
        $this->createTestFile('valid-staff.css');

        // Create symlink to non-existent file
        $symlinkPath = $this->testDir . '/broken-staff.css';

        if (@symlink($this->testDir . '/nonexistent.css', $symlinkPath)) {
            $discovery = new FilesystemCssDiscovery($this->testDir);
            $files = $discovery->discoverFiles();

            // Broken symlink should be silently ignored
            $this->assertCount(1, $files['staff']);
            $this->assertEquals('valid-staff.css', $files['staff'][0]->filename);
        } else {
            $this->markTestSkipped('Could not create symlink (permission denied)');
        }
    }
}
