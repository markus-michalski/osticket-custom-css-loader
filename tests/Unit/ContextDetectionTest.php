<?php

namespace CustomCssLoader\Tests\Unit;

use PHPUnit\Framework\TestCase;
use CustomCssLoaderPlugin;

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
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testDetectsStaffContext(): void
    {
        // Simulate Staff Control Panel context
        define('OSTSCPINC', true);

        $this->assertTrue($this->plugin->isStaffContext());
        $this->assertFalse($this->plugin->isClientContext());
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testDetectsClientContext(): void
    {
        // Simulate Client Portal context
        define('OSTCLIENTINC', true);

        $this->assertFalse($this->plugin->isStaffContext());
        $this->assertTrue($this->plugin->isClientContext());
    }

    /**
     * @test
     */
    public function testReturnsNeitherForUndefinedContext(): void
    {
        // In test environment, neither constant is defined
        // This simulates API or CLI context
        $this->assertFalse($this->plugin->isStaffContext());
        $this->assertFalse($this->plugin->isClientContext());
    }

    /**
     * @test
     */
    public function testGetTargetContextReturnsNull(): void
    {
        // No context defined
        $this->assertNull($this->plugin->getTargetContext());
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGetTargetContextReturnsStaff(): void
    {
        define('OSTSCPINC', true);

        $this->assertEquals('staff', $this->plugin->getTargetContext());
    }

    /**
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGetTargetContextReturnsClient(): void
    {
        define('OSTCLIENTINC', true);

        $this->assertEquals('client', $this->plugin->getTargetContext());
    }
}
