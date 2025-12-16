<?php

declare(strict_types=1);

namespace CustomCssLoader;

use CustomCssLoader\Context\ContextDetectorInterface;
use CustomCssLoader\Discovery\CssFileDiscoveryInterface;
use CustomCssLoader\Discovery\CssFileInfo;
use CustomCssLoader\Injection\InjectionStrategyInterface;
use CustomCssLoader\Rendering\CssRendererInterface;

/**
 * Central orchestrator for CSS loading operations.
 *
 * Coordinates all services:
 * - Context detection (staff/client)
 * - CSS file discovery
 * - Link tag rendering
 * - HTML injection
 *
 * @author Markus Michalski
 * @license GPL-2.0-or-later
 */
class CssLoaderOrchestrator
{
    /**
     * @var string[] Pending CSS link tags to inject
     */
    private array $pendingCssLinks = [];

    /**
     * @var bool Whether CSS has been prepared
     */
    private bool $prepared = false;

    public function __construct(
        private readonly ContextDetectorInterface $contextDetector,
        private readonly CssFileDiscoveryInterface $discovery,
        private readonly CssRendererInterface $renderer,
        private readonly InjectionStrategyInterface $injectionStrategy,
        private bool $enabled = true
    ) {
    }

    /**
     * Prepare CSS for injection (called during bootstrap).
     *
     * Discovers CSS files for the current context and renders link tags.
     * The tags are stored for later injection via output buffer callback.
     *
     * @return void
     */
    public function prepare(): void
    {
        if (!$this->enabled) {
            return;
        }

        $context = $this->contextDetector->detect();

        if ($context === null) {
            return; // No web context (API, CLI, etc.)
        }

        $files = $this->discovery->discoverFiles();
        $targetFiles = $files[$context] ?? [];

        if ($targetFiles === []) {
            return;
        }

        $this->pendingCssLinks = $this->renderer->renderAll($targetFiles);
        $this->prepared = true;
    }

    /**
     * Inject prepared CSS into the output buffer.
     *
     * @param string $buffer The output buffer contents
     * @return string Modified buffer with CSS injected
     */
    public function injectIntoBuffer(string $buffer): string
    {
        return $this->injectionStrategy->inject($buffer, $this->pendingCssLinks);
    }

    /**
     * Get pending CSS link tags (for debugging/testing).
     *
     * @return string[]
     */
    public function getPendingCssLinks(): array
    {
        return $this->pendingCssLinks;
    }

    /**
     * Check if CSS has been prepared.
     *
     * @return bool
     */
    public function isPrepared(): bool
    {
        return $this->prepared;
    }

    /**
     * Enable or disable CSS loading.
     *
     * @param bool $enabled
     * @return void
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * Check if CSS loading is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Clear pending CSS links (for testing).
     *
     * @return void
     */
    public function clear(): void
    {
        $this->pendingCssLinks = [];
        $this->prepared = false;
    }

    /**
     * Get the context detector.
     *
     * @return ContextDetectorInterface
     */
    public function getContextDetector(): ContextDetectorInterface
    {
        return $this->contextDetector;
    }

    /**
     * Get the discovery service.
     *
     * @return CssFileDiscoveryInterface
     */
    public function getDiscovery(): CssFileDiscoveryInterface
    {
        return $this->discovery;
    }

    /**
     * Get the renderer.
     *
     * @return CssRendererInterface
     */
    public function getRenderer(): CssRendererInterface
    {
        return $this->renderer;
    }
}
