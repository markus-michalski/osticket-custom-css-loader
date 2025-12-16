<?php

declare(strict_types=1);

/**
 * Custom CSS Loader Plugin Configuration
 *
 * @author Markus Michalski
 * @license GPL-2.0-or-later
 */

// Only include osTicket classes if they exist (not in test environment)
if (defined('INCLUDE_DIR') && file_exists(INCLUDE_DIR . 'class.plugin.php')) {
    require_once INCLUDE_DIR . 'class.plugin.php';
}

class CustomCssLoaderConfig extends PluginConfig
{
    /**
     * Translation helper for i18n support.
     *
     * @param string $plugin
     * @return array{0: callable(string): string, 1: callable(string, string, int): string}
     */
    public static function translate(string $plugin = 'custom-css-loader'): array
    {
        if (!method_exists('Plugin', 'translate')) {
            return [
                static fn(string $x): string => $x,
                static fn(string $x, string $y, int $n): string => $n !== 1 ? $y : $x,
            ];
        }

        /** @var array{0: callable(string): string, 1: callable(string, string, int): string} */
        return Plugin::translate($plugin);
    }

    /**
     * Get configuration options for admin panel.
     *
     * @return array<string, object>
     */
    public function getOptions(): array
    {
        [$__, $_N] = self::translate();

        return [
            'enabled' => new BooleanField([
                'id' => 'enabled',
                'label' => $__('Enable Custom CSS Loader'),
                'configuration' => [
                    'desc' => $__(
                        'When enabled, CSS files from assets/custom/css/ will be automatically loaded. ' .
                        'Files with "staff" in the name load in Admin Panel, ' .
                        'files with "client" load in Client Portal.'
                    )
                ],
                'default' => true
            ]),

            'installed_version' => new TextboxField([
                'id' => 'installed_version',
                'label' => $__('Installed Version'),
                'configuration' => [
                    'desc' => $__('Currently installed version (automatically updated)'),
                    'size' => 10,
                    'length' => 10,
                    'disabled' => true
                ],
                'default' => ''
            ])
        ];
    }
}
