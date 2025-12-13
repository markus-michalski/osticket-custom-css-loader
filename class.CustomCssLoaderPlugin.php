<?php

/**
 * Custom CSS Loader Plugin for osTicket 1.18.x
 *
 * Automatically loads custom CSS files from assets/custom/css/
 * based on filename patterns (staff/client) for Admin Panel and Client Portal.
 *
 * @author Markus Michalski
 * @license GPL-2.0-or-later
 */

// DEBUG: Log when file is included
error_log('[Custom-CSS-Loader] FILE INCLUDED at ' . date('Y-m-d H:i:s'));

// Only include osTicket classes if they exist (not in test environment)
if (defined('INCLUDE_DIR') && file_exists(INCLUDE_DIR . 'class.plugin.php')) {
    require_once INCLUDE_DIR . 'class.plugin.php';
}

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

/**
 * Output buffer callback for CSS injection
 *
 * This function is called when the output buffer is flushed.
 * It injects CSS link tags before the </head> tag.
 *
 * @param string $buffer The output buffer contents
 * @return string Modified buffer with CSS injected
 */
function custom_css_loader_ob_callback($buffer) {
    $css_tags = CustomCssLoaderPlugin::getCssLinkTags();

    error_log('[Custom-CSS-Loader] OB CALLBACK called, buffer length: ' . strlen($buffer));
    error_log('[Custom-CSS-Loader] CSS tags count: ' . count($css_tags));
    error_log('[Custom-CSS-Loader] Has </head>: ' . (stripos($buffer, '</head>') !== false ? 'YES' : 'NO'));

    if (empty($css_tags) || stripos($buffer, '</head>') === false) {
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

// Start output buffering with callback at file include time (earliest possible)
// This MUST happen before ANY output is sent
if (!defined('CUSTOM_CSS_LOADER_OB_STARTED')) {
    define('CUSTOM_CSS_LOADER_OB_STARTED', true);
    ob_start('custom_css_loader_ob_callback');
}

class CustomCssLoaderPlugin extends Plugin
{
    var $config_class = 'CustomCssLoaderConfig';

    // CSS directory relative to osTicket root
    const CSS_DIR = 'assets/custom/css';

    // Filename patterns (case-insensitive)
    const STAFF_PATTERN = '/staff/i';
    const CLIENT_PATTERN = '/client/i';

    // Test mode properties
    private ?string $testCssDirectory = null;
    private ?string $testContext = null;

    /**
     * Only one instance of this plugin makes sense
     */
    function isSingleton()
    {
        return true;
    }

    // Static cache for CSS link tags to inject
    private static $css_link_tags = [];

    /**
     * Get CSS link tags for injection (called by output buffer callback)
     *
     * @return array Array of HTML link tags
     */
    static function getCssLinkTags()
    {
        return self::$css_link_tags;
    }

    /**
     * Bootstrap plugin - called when osTicket initializes
     *
     * Note: During bootstrap(), the global $ost variable is not yet set.
     * CSS injection happens via output buffer callback registered at file include time.
     */
    function bootstrap()
    {
        // DEBUG: Log bootstrap call
        error_log('[Custom-CSS-Loader] BOOTSTRAP called at ' . date('Y-m-d H:i:s'));

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

        // Get current context
        $context = $this->getTargetContext();
        error_log('[Custom-CSS-Loader] Context detected: ' . ($context ?: 'NONE'));
        error_log('[Custom-CSS-Loader] OSTSCPINC defined: ' . (defined('OSTSCPINC') ? 'YES' : 'NO'));
        error_log('[Custom-CSS-Loader] OSTCLIENTINC defined: ' . (defined('OSTCLIENTINC') ? 'YES' : 'NO'));

        if (!$context) {
            return; // No web context (API, CLI, etc.)
        }

        // Discover and prepare CSS files
        $files = $this->discoverCssFiles();
        $target_files = $files[$context] ?? [];

        error_log('[Custom-CSS-Loader] CSS dir: ' . $this->getCssDirectoryPath());
        error_log('[Custom-CSS-Loader] Found ' . count($target_files) . ' CSS files for context ' . $context);

        if (empty($target_files)) {
            return;
        }

        // Build link tags and store statically for the output buffer callback
        foreach ($target_files as $file_info) {
            $tag = $this->buildLinkTag($file_info);
            self::$css_link_tags[] = $tag;
            error_log('[Custom-CSS-Loader] Added CSS tag: ' . $file_info['filename']);
        }

        error_log('[Custom-CSS-Loader] Total CSS tags stored: ' . count(self::$css_link_tags));
    }

    /**
     * Called when plugin is enabled in admin panel
     */
    function enable()
    {
        $errors = array();

        // Auto-create instance for singleton plugin
        if ($this->isSingleton() && $this->getNumInstances() === 0) {
            $vars = array(
                'name' => $this->getName(),
                'isactive' => 1,
                'notes' => 'Auto-created singleton instance'
            );

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

        return empty($errors) ? true : $errors;
    }

    /**
     * Check plugin version and perform updates if needed
     */
    function checkVersion()
    {
        if (!defined('INCLUDE_DIR')) {
            return;
        }

        $plugin_file = INCLUDE_DIR . 'plugins/' . basename(dirname(__FILE__)) . '/plugin.php';

        if (!file_exists($plugin_file)) {
            return;
        }

        $plugin_info = include($plugin_file);
        $current_version = $plugin_info['version'] ?? '0.0.0';
        $installed_version = $this->getConfig()->get('installed_version');

        if (!$installed_version || version_compare($installed_version, $current_version, '<')) {
            $this->performUpdate($installed_version, $current_version);
        }
    }

    /**
     * Perform plugin update
     */
    function performUpdate($from_version, $to_version)
    {
        // Ensure CSS directory exists on update
        $this->ensureCssDirectory();

        // Save new version
        $this->saveInstalledVersion($to_version);
    }

    /**
     * Save installed version to config
     */
    function saveInstalledVersion($version = null)
    {
        if ($version === null && defined('INCLUDE_DIR')) {
            $plugin_file = INCLUDE_DIR . 'plugins/' . basename(dirname(__FILE__)) . '/plugin.php';
            if (file_exists($plugin_file)) {
                $plugin_info = include($plugin_file);
                $version = $plugin_info['version'] ?? '1.0.0';
            }
        }

        if ($version) {
            $this->getConfig()->set('installed_version', $version);
        }
    }

    /**
     * Create CSS directory if it doesn't exist
     *
     * @return bool Success status
     */
    function ensureCssDirectory()
    {
        $css_dir = $this->getCssDirectoryPath();

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
     * Copy demo files to CSS directory if it's empty
     */
    function copyDemoFilesIfEmpty()
    {
        $css_dir = $this->getCssDirectoryPath();
        $demo_dir = __DIR__ . '/assets/demo';

        // Check if CSS directory is empty
        $existing_files = glob($css_dir . '/*.css');
        if (!empty($existing_files)) {
            return; // Directory not empty, don't overwrite
        }

        // Demo files to copy
        $demo_files = array('custom-staff.css', 'custom-client.css');

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
     * Get the absolute path to the CSS directory
     *
     * @return string CSS directory path
     */
    function getCssDirectoryPath()
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
     * Get the URL path to the CSS directory
     *
     * @return string CSS directory URL
     */
    function getCssDirectoryUrl()
    {
        if (defined('ROOT_PATH')) {
            return ROOT_PATH . self::CSS_DIR;
        }
        return '/' . self::CSS_DIR;
    }

    /**
     * Discover CSS files in the CSS directory
     *
     * @return array ['staff' => [...], 'client' => [...]]
     */
    function discoverCssFiles()
    {
        $css_dir = $this->getCssDirectoryPath();
        $result = ['staff' => [], 'client' => []];

        if (!is_dir($css_dir)) {
            return $result;
        }

        $files = glob($css_dir . '/*.css');
        if (!$files) {
            return $result;
        }

        foreach ($files as $file) {
            $filename = basename($file);
            $file_info = [
                'path' => $file,
                'filename' => $filename,
                'mtime' => filemtime($file)
            ];

            if (preg_match(self::STAFF_PATTERN, $filename)) {
                $result['staff'][] = $file_info;
            } elseif (preg_match(self::CLIENT_PATTERN, $filename)) {
                $result['client'][] = $file_info;
            }
            // Files without staff/client in name are ignored
        }

        return $result;
    }

    /**
     * Check if current context is Staff Control Panel
     *
     * @return bool
     */
    function isStaffContext()
    {
        // Test mode
        if ($this->testContext !== null) {
            return $this->testContext === 'staff';
        }

        // Production: check osTicket constant
        return defined('OSTSCPINC');
    }

    /**
     * Check if current context is Client Portal
     *
     * @return bool
     */
    function isClientContext()
    {
        // Test mode
        if ($this->testContext !== null) {
            return $this->testContext === 'client';
        }

        // Production: check osTicket constant
        return defined('OSTCLIENTINC');
    }

    /**
     * Get current target context
     *
     * @return string|null 'staff', 'client', or null
     */
    function getTargetContext()
    {
        if ($this->isStaffContext()) {
            return 'staff';
        }
        if ($this->isClientContext()) {
            return 'client';
        }
        return null;
    }

    /**
     * Build HTML link tag for a CSS file
     *
     * @param array $file_info File information from discoverCssFiles()
     * @return string HTML link tag
     */
    function buildLinkTag($file_info)
    {
        $url = $this->getCssDirectoryUrl() . '/' . $file_info['filename'];
        $url .= '?v=' . $file_info['mtime']; // Cache-busting

        // Security: escape URL to prevent XSS
        $escaped_url = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

        return '<link rel="stylesheet" href="' . $escaped_url . '">';
    }

    /**
     * Inject CSS files into the page via $ost->addExtraHeader()
     *
     * @param object $ost osTicket main object (or MockOsTicket for tests)
     */
    function injectCssFiles($ost)
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
        if (!$ost || !method_exists($ost, 'addExtraHeader')) {
            error_log('[Custom-CSS-Loader] No $ost object or addExtraHeader method not available');
            return;
        }

        // Get current context
        $context = $this->getTargetContext();
        if (!$context) {
            error_log('[Custom-CSS-Loader] No context detected (not staff and not client)');
            return; // No context (API, CLI, etc.)
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

    // ========== Test Helper Methods ==========

    /**
     * Set test CSS directory (for unit tests)
     *
     * @param string $path
     */
    function setTestCssDirectory($path)
    {
        $this->testCssDirectory = $path;
    }

    /**
     * Set test context (for unit tests)
     *
     * @param string|null $context 'staff', 'client', or null
     */
    function setTestContext($context)
    {
        $this->testContext = $context;
    }
}
