<?php

declare(strict_types=1);

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
    protected PluginConfig $config;

    /** @var array<array<string, mixed>> */
    protected array $instances = [];

    public function __construct()
    {
        $this->config = new PluginConfig();
    }

    public function getConfig(): PluginConfig
    {
        return $this->config;
    }

    public function getName(): string
    {
        return 'Custom CSS Loader';
    }

    public function isSingleton(): bool
    {
        return true;
    }

    public function getNumInstances(): int
    {
        return count($this->instances);
    }

    /**
     * @param array<string, mixed> $vars
     * @param array<string> $errors
     * @return bool
     */
    public function addInstance(array $vars, array &$errors): bool
    {
        $this->instances[] = $vars;
        return true;
    }

    public function bootstrap(): void
    {
        // Override in child classes
    }

    /**
     * @return bool|array<string>
     */
    public function enable(): bool|array
    {
        return true;
    }

    public function disable(): bool
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
    /** @var array<string, mixed> */
    private array $config = [
        'enabled' => true,
        'installed_version' => '1.0.0',
    ];

    public function get(string $key): mixed
    {
        return $this->config[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
    }

    public function getForm(): MockForm
    {
        return new MockForm();
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string> $errors
     * @return bool
     */
    public function pre_save(array &$config = [], array &$errors = []): bool
    {
        return true;
    }

    /**
     * @return array<string, object>
     */
    public function getOptions(): array
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
    public function getField(string $name): MockFormField
    {
        return new MockFormField();
    }
}

/**
 * Mock FormField class
 */
class MockFormField
{
    public function addError(string $message): void
    {
        // No-op in mock
    }
}

/**
 * Mock BooleanField class
 *
 * @phpstan-type BooleanFieldConfig array{id?: string, label?: string, configuration?: array<string, mixed>, default?: bool}
 */
class BooleanField extends MockFormField
{
    /** @var BooleanFieldConfig */
    private array $config;

    /**
     * @param BooleanFieldConfig $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }
}

if (!class_exists('BooleanField', false)) {
    class_alias(BooleanField::class, 'BooleanField');
}

/**
 * Mock TextboxField class
 *
 * @phpstan-type TextboxFieldConfig array{id?: string, label?: string, configuration?: array<string, mixed>, default?: string}
 */
class TextboxField extends MockFormField
{
    /** @var TextboxFieldConfig */
    private array $config;

    /**
     * @param TextboxFieldConfig $config
     */
    public function __construct(array $config)
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
    /** @var array<string, string> */
    private array $headers = [];

    public function addExtraHeader(string $header, bool $pjax_script = false): void
    {
        $this->headers[md5($header)] = $header;
    }

    /**
     * @return array<string, string>
     */
    public function getExtraHeaders(): array
    {
        return $this->headers;
    }

    public function clearHeaders(): void
    {
        $this->headers = [];
    }
}

/**
 * Mock Signal system (osTicket's event system)
 */
class Signal
{
    /** @var array<string, array<callable>> */
    private static array $handlers = [];

    public static function connect(string $signal, callable $callback): void
    {
        if (!isset(self::$handlers[$signal])) {
            self::$handlers[$signal] = [];
        }
        self::$handlers[$signal][] = $callback;
    }

    public static function send(string $signal, mixed ...$args): void
    {
        if (!isset(self::$handlers[$signal])) {
            return;
        }

        foreach (self::$handlers[$signal] as $callback) {
            call_user_func_array($callback, $args);
        }
    }

    /**
     * @return array<callable>
     */
    public static function getHandlers(string $signal): array
    {
        return self::$handlers[$signal] ?? [];
    }

    public static function clearHandlers(?string $signal = null): void
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
