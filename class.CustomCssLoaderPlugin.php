<?php

declare(strict_types=1);

/**
 * Custom CSS Loader Plugin for osTicket 1.18.x
 *
 * Automatically loads custom CSS files from assets/custom/css/
 * based on filename patterns (staff/client) for Admin Panel and Client Portal.
 *
 * @author Markus Michalski
 * @license GPL-2.0-or-later
 */

// PSR-4 autoloader for new architecture (via Composer)
require_once __DIR__ . '/vendor/autoload.php';

// Only include osTicket classes if they exist (not in test environment)
if (defined('INCLUDE_DIR') && file_exists(INCLUDE_DIR . 'class.plugin.php')) {
    require_once INCLUDE_DIR . 'class.plugin.php';
}

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

use CustomCssLoader\Context\ContextDetectorInterface;
use CustomCssLoader\Context\ProductionContextDetector;
use CustomCssLoader\Context\TestContextDetector;
use CustomCssLoader\CssLoaderOrchestrator;
use CustomCssLoader\Discovery\CssFileDiscoveryInterface;
use CustomCssLoader\Discovery\CssFileInfo;
use CustomCssLoader\Discovery\FilesystemCssDiscovery;
use CustomCssLoader\Injection\InjectionStrategyInterface;
use CustomCssLoader\Injection\OutputBufferInjectionStrategy;
use CustomCssLoader\Rendering\CssRendererInterface;
use CustomCssLoader\Rendering\HtmlCssRenderer;

/**
 * Global orchestrator instance for output buffer callback.
 *
 * @var CssLoaderOrchestrator|null
 */
$GLOBALS['__custom_css_loader_orchestrator'] = null;

/**
 * Output buffer callback for CSS injection.
 *
 * This function is called when the output buffer is flushed.
 * It delegates to the orchestrator for actual injection.
 *
 * Security: Wrapped in try-catch to prevent output buffer crashes.
 *
 * @param string $buffer The output buffer contents
 * @return string Modified buffer with CSS injected
 */
function custom_css_loader_ob_callback(string $buffer): string
{
    try {
        $orchestrator = $GLOBALS['__custom_css_loader_orchestrator'] ?? null;

        if ($orchestrator instanceof CssLoaderOrchestrator) {
            return $orchestrator->injectIntoBuffer($buffer);
        }

        // Fallback: Use legacy static method if new orchestrator not initialized
        if (class_exists('CustomCssLoaderPlugin')) {
            return CustomCssLoaderPlugin::legacyInjectCss($buffer);
        }
    } catch (\Throwable $e) {
        error_log(sprintf(
            '[Custom CSS Loader] Output buffer callback failed: %s in %s:%d',
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));
    }

    return $buffer;
}

// Start output buffering with callback at file include time (earliest possible)
// This MUST happen before ANY output is sent
if (!defined('CUSTOM_CSS_LOADER_OB_STARTED')) {
    define('CUSTOM_CSS_LOADER_OB_STARTED', true);
    ob_start('custom_css_loader_ob_callback');
}

class CustomCssLoaderPlugin extends Plugin
{
    /** @var string */
    public $config_class = 'CustomCssLoaderConfig';

    // CSS directory relative to osTicket root
    public const CSS_DIR = 'assets/custom/css';

    // Filename patterns (case-insensitive)
    public const STAFF_PATTERN = '/staff/i';
    public const CLIENT_PATTERN = '/client/i';

    // ========== New Architecture ==========

    private ?CssLoaderOrchestrator $orchestrator = null;
    private ?ContextDetectorInterface $contextDetector = null;
    private ?CssFileDiscoveryInterface $discovery = null;
    private ?CssRendererInterface $renderer = null;
    private ?InjectionStrategyInterface $injectionStrategy = null;

    // ========== Legacy Support ==========

    /** @deprecated Use orchestrator instead */
    private ?string $testCssDirectory = null;

    /** @deprecated Use orchestrator instead */
    private ?string $testContext = null;

    /**
     * @deprecated Use orchestrator instead
     * @var array<string>
     */
    private static array $css_link_tags = [];

    /**
     * Only one instance of this plugin makes sense.
     */
    public function isSingleton(): bool
    {
        return true;
    }

    /**
     * Get CSS link tags for injection (legacy support for output buffer callback).
     *
     * @deprecated Use getOrchestrator()->getPendingCssLinks() instead
     * @return array<string> Array of HTML link tags
     */
    public static function getCssLinkTags(): array
    {
        return self::$css_link_tags;
    }

    /**
     * Legacy injection for backward compatibility with old output buffer callback.
     *
     * @internal
     * @param string $buffer
     * @return string
     */
    public static function legacyInjectCss(string $buffer): string
    {
        $css_tags = self::$css_link_tags;

        if ($css_tags === [] || stripos($buffer, '</head>') === false) {
            return $buffer;
        }

        // Build CSS injection string
        $css_injection = "\n    <!-- Custom CSS Loader Plugin -->\n";
        foreach ($css_tags as $tag) {
            $css_injection .= "    " . $tag . "\n";
        }
        $css_injection .= "    <!-- /Custom CSS Loader Plugin -->\n";

        // Inject before </head>
        return str_ireplace('</head>', $css_injection . '</head>', $buffer);
    }

    /**
     * Bootstrap plugin - called when osTicket initializes.
     *
     * Note: During bootstrap(), the global $ost variable is not yet set.
     * CSS injection happens via output buffer callback registered at file include time.
     */
    public function bootstrap(): void
    {
        // Version tracking and auto-update
        $this->checkVersion();

        // Check if enabled (default to true if not set)
        $enabled = $this->getConfig()->get('enabled');
        if ($enabled === null) {
            $enabled = true; // Default value
        }

        // Skip injection if disabled
        if (!$enabled) {
            return;
        }

        // Initialize and use new orchestrator
        $orchestrator = $this->getOrchestrator();
        $orchestrator->setEnabled((bool) $enabled);
        $orchestrator->prepare();

        // Store in global for output buffer callback
        $GLOBALS['__custom_css_loader_orchestrator'] = $orchestrator;

        // Also populate legacy static array for backward compatibility
        self::$css_link_tags = $orchestrator->getPendingCssLinks();
    }

    /**
     * Get or create the orchestrator.
     *
     * @return CssLoaderOrchestrator
     */
    public function getOrchestrator(): CssLoaderOrchestrator
    {
        if ($this->orchestrator === null) {
            $this->orchestrator = new CssLoaderOrchestrator(
                $this->getContextDetector(),
                $this->getDiscoveryService(),
                $this->getRendererService(),
                $this->getInjectionStrategy()
            );
        }

        return $this->orchestrator;
    }

    /**
     * Get or create the context detector.
     *
     * @return ContextDetectorInterface
     */
    public function getContextDetector(): ContextDetectorInterface
    {
        if ($this->contextDetector === null) {
            // Use test detector if test context is set (legacy support)
            if ($this->testContext !== null) {
                $this->contextDetector = new TestContextDetector($this->testContext);
            } else {
                $this->contextDetector = new ProductionContextDetector();
            }
        }

        return $this->contextDetector;
    }

    /**
     * Get or create the discovery service.
     *
     * @return CssFileDiscoveryInterface
     */
    public function getDiscoveryService(): CssFileDiscoveryInterface
    {
        if ($this->discovery === null) {
            $this->discovery = new FilesystemCssDiscovery(
                $this->getCssDirectoryPath()
            );
        }

        return $this->discovery;
    }

    /**
     * Get or create the renderer service.
     *
     * @return CssRendererInterface
     */
    public function getRendererService(): CssRendererInterface
    {
        if ($this->renderer === null) {
            $this->renderer = new HtmlCssRenderer(
                $this->getCssDirectoryUrl()
            );
        }

        return $this->renderer;
    }

    /**
     * Get or create the injection strategy.
     *
     * @return InjectionStrategyInterface
     */
    public function getInjectionStrategy(): InjectionStrategyInterface
    {
        if ($this->injectionStrategy === null) {
            $this->injectionStrategy = new OutputBufferInjectionStrategy();
        }

        return $this->injectionStrategy;
    }

    /**
     * Set custom context detector (for testing).
     *
     * @param ContextDetectorInterface $detector
     * @return void
     */
    public function setContextDetector(ContextDetectorInterface $detector): void
    {
        $this->contextDetector = $detector;
        $this->orchestrator = null; // Reset orchestrator to use new detector
    }

    /**
     * Set custom discovery service (for testing).
     *
     * @param CssFileDiscoveryInterface $discovery
     * @return void
     */
    public function setDiscoveryService(CssFileDiscoveryInterface $discovery): void
    {
        $this->discovery = $discovery;
        $this->orchestrator = null; // Reset orchestrator to use new discovery
    }

    /**
     * Set custom renderer (for testing).
     *
     * @param CssRendererInterface $renderer
     * @return void
     */
    public function setRendererService(CssRendererInterface $renderer): void
    {
        $this->renderer = $renderer;
        $this->orchestrator = null; // Reset orchestrator to use new renderer
    }

    /**
     * Set custom injection strategy (for testing).
     *
     * @param InjectionStrategyInterface $strategy
     * @return void
     */
    public function setInjectionStrategy(InjectionStrategyInterface $strategy): void
    {
        $this->injectionStrategy = $strategy;
        $this->orchestrator = null; // Reset orchestrator to use new strategy
    }

    /**
     * Called when plugin is enabled in admin panel.
     *
     * @return bool|array<string>
     */
    public function enable(): bool|array
    {
        $errors = [];

        // Auto-create instance for singleton plugin
        if ($this->isSingleton() && $this->getNumInstances() === 0) {
            $vars = [
                'name' => $this->getName(),
                'isactive' => 1,
                'notes' => 'Auto-created singleton instance'
            ];

            if (!$this->addInstance($vars, $errors)) {
                return $errors;
            }
        }

        // Create CSS directory if it doesn't exist
        $this->ensureCssDirectory();

        // Copy demo files if directory is empty
        $this->copyDemoFilesIfEmpty();

        // Save installed version
        $this->saveInstalledVersion();

        return true;
    }

    /**
     * Check plugin version and perform updates if needed.
     */
    public function checkVersion(): void
    {
        if (!defined('INCLUDE_DIR')) {
            return;
        }

        $plugin_file = INCLUDE_DIR . 'plugins/' . basename(__DIR__) . '/plugin.php';

        if (!file_exists($plugin_file)) {
            return;
        }

        $plugin_info = include $plugin_file;
        $current_version = $plugin_info['version'] ?? '0.0.0';
        $installed_version = $this->getConfig()->get('installed_version');

        if (!$installed_version || version_compare($installed_version, $current_version, '<')) {
            $this->performUpdate($installed_version, $current_version);
        }
    }

    /**
     * Perform plugin update.
     *
     * @param string|null $from_version
     * @param string $to_version
     */
    public function performUpdate(?string $from_version, string $to_version): void
    {
        // Ensure CSS directory exists on update
        $this->ensureCssDirectory();

        // Save new version
        $this->saveInstalledVersion($to_version);
    }

    /**
     * Save installed version to config.
     *
     * @param string|null $version
     */
    public function saveInstalledVersion(?string $version = null): void
    {
        if ($version === null && defined('INCLUDE_DIR')) {
            $plugin_file = INCLUDE_DIR . 'plugins/' . basename(__DIR__) . '/plugin.php';
            if (file_exists($plugin_file)) {
                $plugin_info = include $plugin_file;
                $version = $plugin_info['version'] ?? '1.0.0';
            }
        }

        if ($version !== null) {
            $this->getConfig()->set('installed_version', $version);
        }
    }

    /**
     * Create CSS directory if it doesn't exist.
     *
     * @return bool Success status
     */
    public function ensureCssDirectory(): bool
    {
        $css_dir = $this->getCssDirectoryPath();

        if ($css_dir === '') {
            return false;
        }

        if (!is_dir($css_dir)) {
            if (!@mkdir($css_dir, 0755, true)) {
                error_log('[Custom-CSS-Loader] Failed to create directory: ' . $css_dir);
                return false;
            }
            error_log('[Custom-CSS-Loader] Created CSS directory: ' . $css_dir);
        }

        return true;
    }

    /**
     * Copy demo files to CSS directory if it's empty.
     */
    public function copyDemoFilesIfEmpty(): void
    {
        $css_dir = $this->getCssDirectoryPath();
        $demo_dir = __DIR__ . '/assets/demo';

        if ($css_dir === '') {
            return;
        }

        // Check if CSS directory is empty
        $existing_files = glob($css_dir . '/*.css');
        if ($existing_files !== false && $existing_files !== []) {
            return; // Directory not empty, don't overwrite
        }

        // Demo files to copy
        $demo_files = ['custom-staff.css', 'custom-client.css'];

        foreach ($demo_files as $file) {
            $source = $demo_dir . '/' . $file;
            $target = $css_dir . '/' . $file;

            if (file_exists($source) && !file_exists($target)) {
                if (@copy($source, $target)) {
                    error_log('[Custom-CSS-Loader] Copied demo file: ' . $file);
                }
            }
        }
    }

    /**
     * Get the absolute path to the CSS directory.
     *
     * @return string CSS directory path
     */
    public function getCssDirectoryPath(): string
    {
        // Test mode: use test directory
        if ($this->testCssDirectory !== null) {
            return $this->testCssDirectory;
        }

        // Production: osTicket root / assets/custom/css
        if (defined('INCLUDE_DIR')) {
            $osticket_root = dirname(INCLUDE_DIR);
            return $osticket_root . '/' . self::CSS_DIR;
        }

        return '';
    }

    /**
     * Get the URL path to the CSS directory.
     *
     * @return string CSS directory URL
     */
    public function getCssDirectoryUrl(): string
    {
        if (defined('ROOT_PATH')) {
            return ROOT_PATH . self::CSS_DIR;
        }
        return '/' . self::CSS_DIR;
    }

    /**
     * Discover CSS files in the CSS directory.
     *
     * @return array{staff: array<array{path: string, filename: string, mtime: int}>, client: array<array{path: string, filename: string, mtime: int}>}
     */
    public function discoverCssFiles(): array
    {
        $discovery = $this->getDiscoveryService();
        $files = $discovery->discoverFiles();

        // Convert CssFileInfo objects to arrays for backward compatibility
        return [
            'staff' => array_map(
                fn(CssFileInfo $f): array => $f->toArray(),
                $files['staff']
            ),
            'client' => array_map(
                fn(CssFileInfo $f): array => $f->toArray(),
                $files['client']
            ),
        ];
    }

    /**
     * Check if current context is Staff Control Panel.
     *
     * @return bool
     */
    public function isStaffContext(): bool
    {
        return $this->getContextDetector()->isStaff();
    }

    /**
     * Check if current context is Client Portal.
     *
     * @return bool
     */
    public function isClientContext(): bool
    {
        return $this->getContextDetector()->isClient();
    }

    /**
     * Get current target context.
     *
     * @return string|null 'staff', 'client', or null
     */
    public function getTargetContext(): ?string
    {
        return $this->getContextDetector()->detect();
    }

    /**
     * Build HTML link tag for a CSS file.
     *
     * @param array{path: string, filename: string, mtime: int} $file_info File information from discoverCssFiles()
     * @return string HTML link tag
     */
    public function buildLinkTag(array $file_info): string
    {
        $cssFileInfo = CssFileInfo::fromArray($file_info);
        return $this->getRendererService()->render($cssFileInfo);
    }

    /**
     * Inject CSS files into the page via $ost->addExtraHeader().
     *
     * @param object $ost osTicket main object (or MockOsTicket for tests)
     */
    public function injectCssFiles(object $ost): void
    {
        // Check if plugin is enabled (default to true if not set)
        $enabled = $this->getConfig()->get('enabled');
        if ($enabled === null) {
            $enabled = true;
        }
        if (!$enabled) {
            return;
        }

        // Check if $ost supports addExtraHeader
        if (!method_exists($ost, 'addExtraHeader')) {
            error_log('[Custom-CSS-Loader] No $ost object or addExtraHeader method not available');
            return;
        }

        // Get current context
        $context = $this->getTargetContext();
        if ($context === null) {
            error_log('[Custom-CSS-Loader] No context detected (not staff and not client)');
            return;
        }

        // Discover and inject CSS files
        $files = $this->discoverCssFiles();
        $target_files = $files[$context] ?? [];

        error_log('[Custom-CSS-Loader] Context: ' . $context . ', Found ' . count($target_files) . ' CSS files');

        foreach ($target_files as $file_info) {
            $link_tag = $this->buildLinkTag($file_info);
            $ost->addExtraHeader($link_tag);
            error_log('[Custom-CSS-Loader] Injected: ' . $file_info['filename']);
        }
    }

    // ========== Legacy Test Helper Methods ==========

    /**
     * Set test CSS directory (for unit tests).
     *
     * @deprecated Use setDiscoveryService() with FilesystemCssDiscovery instead
     * @param string $path
     */
    public function setTestCssDirectory(string $path): void
    {
        $this->testCssDirectory = $path;
        // Reset discovery to use new path
        $this->discovery = new FilesystemCssDiscovery($path);
        $this->orchestrator = null;
    }

    /**
     * Set test context (for unit tests).
     *
     * @deprecated Use setContextDetector() with TestContextDetector instead
     * @param string|null $context 'staff', 'client', or null
     */
    public function setTestContext(?string $context): void
    {
        $this->testContext = $context;
        // Reset context detector
        $this->contextDetector = new TestContextDetector($context);
        $this->orchestrator = null;
    }

    /**
     * Clear static state (for testing).
     *
     * @return void
     */
    public static function clearStaticState(): void
    {
        self::$css_link_tags = [];
        $GLOBALS['__custom_css_loader_orchestrator'] = null;
    }
}
