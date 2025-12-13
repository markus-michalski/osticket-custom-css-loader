<?php

namespace CustomCssLoader\Tests\Mocks;

/**
 * Mock osTicket Classes for Testing
 *
 * These mocks allow testing the Custom CSS Loader plugin without requiring
 * a full osTicket installation.
 */

/**
 * Mock Plugin base class
 */
class Plugin
{
    protected $config;
    protected $instances = [];

    public function __construct()
    {
        $this->config = new PluginConfig();
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function getName()
    {
        return 'Custom CSS Loader';
    }

    public function isSingleton()
    {
        return true;
    }

    public function getNumInstances()
    {
        return count($this->instances);
    }

    public function addInstance($vars, &$errors)
    {
        $this->instances[] = $vars;
        return true;
    }

    public function bootstrap()
    {
        // Override in child classes
    }

    public function enable()
    {
        return true;
    }

    public function disable()
    {
        return true;
    }
}

// Make Plugin available globally for class.CustomCssLoaderPlugin.php
if (!class_exists('Plugin', false)) {
    class_alias(Plugin::class, 'Plugin');
}

/**
 * Mock PluginConfig class
 */
class PluginConfig
{
    private $config = [
        'enabled' => true,
        'installed_version' => '1.0.0',
    ];

    public function get($key)
    {
        return $this->config[$key] ?? null;
    }

    public function set($key, $value)
    {
        $this->config[$key] = $value;
    }

    public function getForm()
    {
        return new MockForm();
    }

    public function pre_save(&$config = [], &$errors = [])
    {
        return true;
    }

    public function getOptions()
    {
        return [];
    }
}

// Make PluginConfig available globally
if (!class_exists('PluginConfig', false)) {
    class_alias(PluginConfig::class, 'PluginConfig');
}

/**
 * Mock Form class (for PluginConfig validation)
 */
class MockForm
{
    public function getField($name)
    {
        return new MockFormField();
    }
}

/**
 * Mock FormField class
 */
class MockFormField
{
    public function addError($message)
    {
        // No-op in mock
    }
}

/**
 * Mock BooleanField class
 */
class BooleanField extends MockFormField
{
    private $config;

    public function __construct($config)
    {
        $this->config = $config;
    }
}

if (!class_exists('BooleanField', false)) {
    class_alias(BooleanField::class, 'BooleanField');
}

/**
 * Mock TextboxField class
 */
class TextboxField extends MockFormField
{
    private $config;

    public function __construct($config)
    {
        $this->config = $config;
    }
}

if (!class_exists('TextboxField', false)) {
    class_alias(TextboxField::class, 'TextboxField');
}

/**
 * Mock osTicket main class with header injection support
 */
class MockOsTicket
{
    private $headers = [];

    public function addExtraHeader($header, $pjax_script = false)
    {
        $this->headers[md5($header)] = $header;
    }

    public function getExtraHeaders()
    {
        return $this->headers;
    }

    public function clearHeaders()
    {
        $this->headers = [];
    }
}

/**
 * Mock Signal system (osTicket's event system)
 */
class Signal
{
    private static $handlers = [];

    public static function connect($signal, $callback)
    {
        if (!isset(self::$handlers[$signal])) {
            self::$handlers[$signal] = [];
        }
        self::$handlers[$signal][] = $callback;
    }

    public static function send($signal, ...$args)
    {
        if (!isset(self::$handlers[$signal])) {
            return;
        }

        foreach (self::$handlers[$signal] as $callback) {
            call_user_func_array($callback, $args);
        }
    }

    public static function getHandlers($signal)
    {
        return self::$handlers[$signal] ?? [];
    }

    public static function clearHandlers($signal = null)
    {
        if ($signal === null) {
            self::$handlers = [];
        } else {
            self::$handlers[$signal] = [];
        }
    }
}

// Make Signal available globally
if (!class_exists('Signal', false)) {
    class_alias(Signal::class, 'Signal');
}
