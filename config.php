<?php

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
     * Translation helper for i18n support
     */
    static function translate($plugin = 'custom-css-loader')
    {
        if (!method_exists('Plugin', 'translate')) {
            return array(
                function ($x) {
                    return $x;
                },
                function ($x, $y, $n) {
                    return $n != 1 ? $y : $x;
                },
            );
        }
        return Plugin::translate($plugin);
    }

    /**
     * Get configuration options for admin panel
     */
    function getOptions()
    {
        list($__, $_N) = self::translate();

        return array(
            'enabled' => new BooleanField(array(
                'id' => 'enabled',
                'label' => $__('Enable Custom CSS Loader'),
                'configuration' => array(
                    'desc' => $__('When enabled, CSS files from assets/custom/css/ will be automatically loaded. ' .
                        'Files with "staff" in the name load in Admin Panel, ' .
                        'files with "client" load in Client Portal.')
                ),
                'default' => true
            )),

            'installed_version' => new TextboxField(array(
                'id' => 'installed_version',
                'label' => $__('Installed Version'),
                'configuration' => array(
                    'desc' => $__('Currently installed version (automatically updated)'),
                    'size' => 10,
                    'length' => 10,
                    'disabled' => true
                ),
                'default' => ''
            ))
        );
    }
}
