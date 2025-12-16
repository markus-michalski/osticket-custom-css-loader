<?php

declare(strict_types=1);

namespace CustomCssLoader\Tests\Unit;

use CustomCssLoader\Context\ContextDetectorInterface;
use CustomCssLoader\Context\ProductionContextDetector;
use CustomCssLoader\Context\TestContextDetector;
use CustomCssLoaderPlugin;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Staff/Client context detection
 */
class ContextDetectionTest extends TestCase
{
    private CustomCssLoaderPlugin $plugin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plugin = new CustomCssLoaderPlugin();

        // Clear static state
        CustomCssLoaderPlugin::clearStaticState();
    }

    protected function tearDown(): void
    {
        // Reset test context after each test
        $this->plugin->setTestContext(null);

        // Clear static state
        CustomCssLoaderPlugin::clearStaticState();

        parent::tearDown();
    }

    #[Test]
    public function detectsStaffContext(): void
    {
        // Use test mode to simulate Staff Control Panel context
        $this->plugin->setTestContext('staff');

        $this->assertTrue($this->plugin->isStaffContext());
        $this->assertFalse($this->plugin->isClientContext());
    }

    #[Test]
    public function detectsClientContext(): void
    {
        // Use test mode to simulate Client Portal context
        $this->plugin->setTestContext('client');

        $this->assertFalse($this->plugin->isStaffContext());
        $this->assertTrue($this->plugin->isClientContext());
    }

    #[Test]
    public function returnsNeitherForUndefinedContext(): void
    {
        // Test mode with null simulates no context (API/CLI)
        $this->plugin->setTestContext(null);

        // In test environment with null context, neither should match
        $this->assertFalse($this->plugin->isStaffContext());
        $this->assertFalse($this->plugin->isClientContext());
    }

    #[Test]
    public function getTargetContextReturnsNull(): void
    {
        // Explicitly set no context
        $this->plugin->setTestContext(null);

        $result = $this->plugin->getTargetContext();

        $this->assertNull($result);
    }

    #[Test]
    public function getTargetContextReturnsStaff(): void
    {
        $this->plugin->setTestContext('staff');

        $this->assertEquals('staff', $this->plugin->getTargetContext());
    }

    #[Test]
    public function getTargetContextReturnsClient(): void
    {
        $this->plugin->setTestContext('client');

        $this->assertEquals('client', $this->plugin->getTargetContext());
    }

    #[Test]
    public function staffContextPrioritizesTestMode(): void
    {
        // Even if constants would match, test mode should take precedence
        $this->plugin->setTestContext('client');

        // Should be client, not staff, regardless of any other conditions
        $this->assertFalse($this->plugin->isStaffContext());
        $this->assertTrue($this->plugin->isClientContext());
    }

    // ========== New Architecture Tests ==========

    #[Test]
    public function testContextDetectorInterface(): void
    {
        $detector = new TestContextDetector('staff');

        $this->assertEquals(ContextDetectorInterface::CONTEXT_STAFF, $detector->detect());
        $this->assertTrue($detector->isStaff());
        $this->assertFalse($detector->isClient());
    }

    #[Test]
    public function testContextDetectorCanBeChanged(): void
    {
        $detector = new TestContextDetector('staff');
        $detector->setContext('client');

        $this->assertEquals('client', $detector->detect());
        $this->assertFalse($detector->isStaff());
        $this->assertTrue($detector->isClient());
    }

    #[Test]
    public function productionContextDetectorWithServerOverride(): void
    {
        $detector = new ProductionContextDetector();

        // Simulate staff panel request
        $detector->setServerVariables(['SCRIPT_NAME' => '/scp/tickets.php']);
        $this->assertTrue($detector->isStaff());
        $this->assertFalse($detector->isClient());

        // Simulate client portal request
        $detector->setServerVariables(['SCRIPT_NAME' => '/tickets.php']);
        $this->assertFalse($detector->isStaff());
        $this->assertTrue($detector->isClient());

        // Simulate API request
        $detector->setServerVariables(['SCRIPT_NAME' => '/api/tickets.json']);
        $this->assertFalse($detector->isStaff());
        $this->assertFalse($detector->isClient());
        $this->assertNull($detector->detect());
    }

    #[Test]
    public function pluginAcceptsCustomContextDetector(): void
    {
        $customDetector = new TestContextDetector('staff');
        $this->plugin->setContextDetector($customDetector);

        $this->assertEquals('staff', $this->plugin->getTargetContext());
        $this->assertTrue($this->plugin->isStaffContext());

        // Change detector's context
        $customDetector->setContext('client');

        $this->assertEquals('client', $this->plugin->getTargetContext());
        $this->assertTrue($this->plugin->isClientContext());
    }
}
