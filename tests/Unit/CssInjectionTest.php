<?php

namespace CustomCssLoader\Tests\Unit;

use PHPUnit\Framework\TestCase;
use CustomCssLoaderPlugin;
use CustomCssLoader\Tests\Mocks\MockOsTicket;

/**
 * Tests for CSS header injection functionality
 */
class CssInjectionTest extends TestCase
{
    private string $testDir;
    private CustomCssLoaderPlugin $plugin;
    private MockOsTicket $mockOst;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temporary test directory
        $this->testDir = sys_get_temp_dir() . '/osticket-css-loader-test-' . uniqid();
        mkdir($this->testDir, 0755, true);

        $this->plugin = new CustomCssLoaderPlugin();
        $this->plugin->setTestCssDirectory($this->testDir);

        $this->mockOst = new MockOsTicket();
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
    public function testBuildsCssLinkTag(): void
    {
        $this->createTestFile('test-staff.css');
        $files = $this->plugin->discoverCssFiles();

        $link = $this->plugin->buildLinkTag($files['staff'][0]);

        $this->assertStringContainsString('<link rel="stylesheet"', $link);
        $this->assertStringContainsString('href="', $link);
        $this->assertStringContainsString('test-staff.css', $link);
    }

    /**
     * @test
     */
    public function testAddsFilemtimeToUrl(): void
    {
        $this->createTestFile('test-staff.css');
        $files = $this->plugin->discoverCssFiles();

        $link = $this->plugin->buildLinkTag($files['staff'][0]);

        // Should contain ?v= parameter with mtime
        $this->assertMatchesRegularExpression('/\?v=\d+/', $link);
    }

    /**
     * @test
     */
    public function testCacheParamChangesOnFileUpdate(): void
    {
        $filename = 'test-staff.css';
        $this->createTestFile($filename);

        $files1 = $this->plugin->discoverCssFiles();
        $mtime1 = $files1['staff'][0]['mtime'];

        // Wait and update file
        sleep(1);
        file_put_contents($this->testDir . '/' . $filename, '/* updated */');
        clearstatcache();

        $files2 = $this->plugin->discoverCssFiles();
        $mtime2 = $files2['staff'][0]['mtime'];

        $this->assertGreaterThan($mtime1, $mtime2);
    }

    /**
     * @test
     */
    public function testInjectsStaffCssInStaffContext(): void
    {
        $this->createTestFile('my-staff-theme.css');
        $this->createTestFile('client-portal.css');

        // Simulate staff context
        $this->plugin->setTestContext('staff');

        $this->plugin->injectCssFiles($this->mockOst);

        $headers = $this->mockOst->getExtraHeaders();
        $headerString = implode("\n", $headers);

        $this->assertStringContainsString('my-staff-theme.css', $headerString);
        $this->assertStringNotContainsString('client-portal.css', $headerString);
    }

    /**
     * @test
     */
    public function testInjectsClientCssInClientContext(): void
    {
        $this->createTestFile('my-staff-theme.css');
        $this->createTestFile('client-portal.css');

        // Simulate client context
        $this->plugin->setTestContext('client');

        $this->plugin->injectCssFiles($this->mockOst);

        $headers = $this->mockOst->getExtraHeaders();
        $headerString = implode("\n", $headers);

        $this->assertStringContainsString('client-portal.css', $headerString);
        $this->assertStringNotContainsString('my-staff-theme.css', $headerString);
    }

    /**
     * @test
     */
    public function testDoesNotInjectWhenDisabled(): void
    {
        $this->createTestFile('test-staff.css');
        $this->plugin->setTestContext('staff');

        // Disable plugin via config
        $this->plugin->getConfig()->set('enabled', false);

        $this->plugin->injectCssFiles($this->mockOst);

        $headers = $this->mockOst->getExtraHeaders();
        $this->assertEmpty($headers);
    }

    /**
     * @test
     */
    public function testDoesNotInjectInUndefinedContext(): void
    {
        $this->createTestFile('test-staff.css');
        $this->createTestFile('test-client.css');

        // No context set (API/CLI)
        $this->plugin->setTestContext(null);

        $this->plugin->injectCssFiles($this->mockOst);

        $headers = $this->mockOst->getExtraHeaders();
        $this->assertEmpty($headers);
    }

    /**
     * @test
     */
    public function testHtmlEscapesUrl(): void
    {
        // Create file with potentially dangerous characters
        $this->createTestFile('staff-test.css');

        $files = $this->plugin->discoverCssFiles();
        $link = $this->plugin->buildLinkTag($files['staff'][0]);

        // Should use htmlspecialchars for the URL
        $this->assertStringNotContainsString('<script>', $link);
        $this->assertStringContainsString('href="', $link);
    }

    /**
     * @test
     */
    public function testInjectsMultipleCssFiles(): void
    {
        $this->createTestFile('staff-theme-1.css');
        $this->createTestFile('staff-theme-2.css');
        $this->createTestFile('my-staff-overrides.css');

        $this->plugin->setTestContext('staff');

        $this->plugin->injectCssFiles($this->mockOst);

        $headers = $this->mockOst->getExtraHeaders();

        // Should have 3 staff CSS files
        $this->assertCount(3, $headers);
    }
}
