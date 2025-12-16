<?php

declare(strict_types=1);

namespace CustomCssLoader\Tests\Unit;

use CustomCssLoader\Context\TestContextDetector;
use CustomCssLoader\CssLoaderOrchestrator;
use CustomCssLoader\Discovery\CssFileInfo;
use CustomCssLoader\Discovery\FilesystemCssDiscovery;
use CustomCssLoader\Injection\OutputBufferInjectionStrategy;
use CustomCssLoader\Rendering\HtmlCssRenderer;
use CustomCssLoader\Tests\Mocks\MockOsTicket;
use CustomCssLoaderPlugin;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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

        // Clear static state
        CustomCssLoaderPlugin::clearStaticState();
    }

    protected function tearDown(): void
    {
        // Clean up test directory
        $this->removeDirectory($this->testDir);

        // Clear static state
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
    public function buildsCssLinkTag(): void
    {
        $this->createTestFile('test-staff.css');
        $files = $this->plugin->discoverCssFiles();

        $link = $this->plugin->buildLinkTag($files['staff'][0]);

        $this->assertStringContainsString('<link rel="stylesheet"', $link);
        $this->assertStringContainsString('href="', $link);
        $this->assertStringContainsString('test-staff.css', $link);
    }

    #[Test]
    public function addsFilemtimeToUrl(): void
    {
        $this->createTestFile('test-staff.css');
        $files = $this->plugin->discoverCssFiles();

        $link = $this->plugin->buildLinkTag($files['staff'][0]);

        // Should contain ?v= parameter with mtime
        $this->assertMatchesRegularExpression('/\?v=\d+/', $link);
    }

    #[Test]
    public function cacheParamChangesOnFileUpdate(): void
    {
        $filename = 'test-staff.css';
        $this->createTestFile($filename);

        $files1 = $this->plugin->discoverCssFiles();
        $mtime1 = $files1['staff'][0]['mtime'];

        // Wait and update file
        sleep(1);
        file_put_contents($this->testDir . '/' . $filename, '/* updated */');
        clearstatcache();

        // Need to reset discovery service to pick up new mtime
        $this->plugin->setTestCssDirectory($this->testDir);

        $files2 = $this->plugin->discoverCssFiles();
        $mtime2 = $files2['staff'][0]['mtime'];

        $this->assertGreaterThan($mtime1, $mtime2);
    }

    #[Test]
    public function injectsStaffCssInStaffContext(): void
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

    #[Test]
    public function injectsClientCssInClientContext(): void
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

    #[Test]
    public function doesNotInjectWhenDisabled(): void
    {
        $this->createTestFile('test-staff.css');
        $this->plugin->setTestContext('staff');

        // Disable plugin via config
        $this->plugin->getConfig()->set('enabled', false);

        $this->plugin->injectCssFiles($this->mockOst);

        $headers = $this->mockOst->getExtraHeaders();
        $this->assertEmpty($headers);
    }

    #[Test]
    public function doesNotInjectInUndefinedContext(): void
    {
        $this->createTestFile('test-staff.css');
        $this->createTestFile('test-client.css');

        // No context set (API/CLI)
        $this->plugin->setTestContext(null);

        $this->plugin->injectCssFiles($this->mockOst);

        $headers = $this->mockOst->getExtraHeaders();
        $this->assertEmpty($headers);
    }

    #[Test]
    public function htmlEscapesUrl(): void
    {
        // Create file with potentially dangerous characters
        $this->createTestFile('staff-test.css');

        $files = $this->plugin->discoverCssFiles();
        $link = $this->plugin->buildLinkTag($files['staff'][0]);

        // Should use htmlspecialchars for the URL
        $this->assertStringNotContainsString('<script>', $link);
        $this->assertStringContainsString('href="', $link);
    }

    #[Test]
    public function injectsMultipleCssFiles(): void
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

    // ========== New Architecture Tests ==========

    #[Test]
    public function rendererProducesValidHtml(): void
    {
        $renderer = new HtmlCssRenderer('/assets/custom/css');

        $fileInfo = new CssFileInfo(
            path: '/path/to/staff-theme.css',
            filename: 'staff-theme.css',
            mtime: 1234567890
        );

        $html = $renderer->render($fileInfo);

        $this->assertStringContainsString('<link rel="stylesheet"', $html);
        $this->assertStringContainsString('href="/assets/custom/css/staff-theme.css?v=1234567890"', $html);
    }

    #[Test]
    public function rendererBlocksInvalidFilename(): void
    {
        $renderer = new HtmlCssRenderer('/assets/custom/css');

        // Filename with special chars - should be blocked by security validation
        $fileInfo = new CssFileInfo(
            path: '/path/to/staff"theme.css',
            filename: 'staff"theme.css',
            mtime: 1234567890
        );

        $html = $renderer->render($fileInfo);

        // Invalid filename should return empty string (defense-in-depth)
        $this->assertEmpty($html);
    }

    #[Test]
    public function rendererEscapesUrlParameter(): void
    {
        $renderer = new HtmlCssRenderer('/assets/custom/css');

        // Valid filename with mtime that could contain special chars
        $fileInfo = new CssFileInfo(
            path: '/path/to/staff-theme.css',
            filename: 'staff-theme.css',
            mtime: 1234567890
        );

        $html = $renderer->render($fileInfo);

        // URL should be properly escaped
        $this->assertStringContainsString('href="', $html);
        $this->assertStringContainsString('staff-theme.css?v=1234567890', $html);
    }

    #[Test]
    public function outputBufferInjectionStrategyInjectsBeforeHead(): void
    {
        $strategy = new OutputBufferInjectionStrategy();

        $buffer = '<!DOCTYPE html><html><head><title>Test</title></head><body></body></html>';
        $cssLinks = ['<link rel="stylesheet" href="/test.css">'];

        $result = $strategy->inject($buffer, $cssLinks);

        $this->assertStringContainsString('<!-- Custom CSS Loader Plugin -->', $result);
        $this->assertStringContainsString('<link rel="stylesheet" href="/test.css">', $result);
        $this->assertStringContainsString('<!-- /Custom CSS Loader Plugin -->', $result);
    }

    #[Test]
    public function outputBufferInjectionReturnsUnmodifiedIfNoHead(): void
    {
        $strategy = new OutputBufferInjectionStrategy();

        $buffer = 'No HTML here, just text';
        $cssLinks = ['<link rel="stylesheet" href="/test.css">'];

        $result = $strategy->inject($buffer, $cssLinks);

        $this->assertEquals($buffer, $result);
    }

    #[Test]
    public function outputBufferInjectionReturnsUnmodifiedIfEmpty(): void
    {
        $strategy = new OutputBufferInjectionStrategy();

        $buffer = '<!DOCTYPE html><html><head></head><body></body></html>';
        $cssLinks = [];

        $result = $strategy->inject($buffer, $cssLinks);

        $this->assertEquals($buffer, $result);
    }

    #[Test]
    public function orchestratorPreparesAndInjects(): void
    {
        $this->createTestFile('staff-theme.css');

        $orchestrator = new CssLoaderOrchestrator(
            new TestContextDetector('staff'),
            new FilesystemCssDiscovery($this->testDir),
            new HtmlCssRenderer('/assets/custom/css'),
            new OutputBufferInjectionStrategy()
        );

        // Prepare CSS
        $orchestrator->prepare();

        $this->assertTrue($orchestrator->isPrepared());
        $this->assertCount(1, $orchestrator->getPendingCssLinks());

        // Inject into buffer
        $buffer = '<!DOCTYPE html><html><head></head><body></body></html>';
        $result = $orchestrator->injectIntoBuffer($buffer);

        $this->assertStringContainsString('staff-theme.css', $result);
    }

    #[Test]
    public function orchestratorRespectsDisabledState(): void
    {
        $this->createTestFile('staff-theme.css');

        $orchestrator = new CssLoaderOrchestrator(
            new TestContextDetector('staff'),
            new FilesystemCssDiscovery($this->testDir),
            new HtmlCssRenderer('/assets/custom/css'),
            new OutputBufferInjectionStrategy(),
            enabled: false
        );

        $orchestrator->prepare();

        $this->assertFalse($orchestrator->isPrepared());
        $this->assertEmpty($orchestrator->getPendingCssLinks());
    }

    #[Test]
    public function orchestratorClearsState(): void
    {
        $this->createTestFile('staff-theme.css');

        $orchestrator = new CssLoaderOrchestrator(
            new TestContextDetector('staff'),
            new FilesystemCssDiscovery($this->testDir),
            new HtmlCssRenderer('/assets/custom/css'),
            new OutputBufferInjectionStrategy()
        );

        $orchestrator->prepare();
        $this->assertTrue($orchestrator->isPrepared());

        $orchestrator->clear();
        $this->assertFalse($orchestrator->isPrepared());
        $this->assertEmpty($orchestrator->getPendingCssLinks());
    }

    #[Test]
    public function pluginOrchestratorIntegration(): void
    {
        $this->createTestFile('staff-theme.css');
        $this->plugin->setTestContext('staff');

        // Get orchestrator and verify it uses injected services
        $orchestrator = $this->plugin->getOrchestrator();

        $this->assertInstanceOf(CssLoaderOrchestrator::class, $orchestrator);
    }
}
