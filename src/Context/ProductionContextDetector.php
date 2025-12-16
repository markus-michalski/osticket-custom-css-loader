<?php

declare(strict_types=1);

namespace CustomCssLoader\Context;

/**
 * Production context detector using osTicket constants and request paths.
 *
 * Detection priority:
 * 1. osTicket constants (OSTSCPINC, OSTCLIENTINC) - most reliable
 * 2. Request path analysis - fallback during early bootstrap
 *
 * @author Markus Michalski
 * @license GPL-2.0-or-later
 */
class ProductionContextDetector implements ContextDetectorInterface
{
    /**
     * @var array<string, mixed>|null Cached server variables for testing
     */
    private ?array $serverOverride = null;

    /**
     * Detect the current context.
     *
     * @return string|null 'staff', 'client', or null (API/CLI/unknown)
     */
    public function detect(): ?string
    {
        if ($this->isStaff()) {
            return self::CONTEXT_STAFF;
        }

        if ($this->isClient()) {
            return self::CONTEXT_CLIENT;
        }

        return null;
    }

    /**
     * Check if current context is staff panel.
     *
     * @return bool
     */
    public function isStaff(): bool
    {
        // Check constant first (may be set after bootstrap)
        if (defined('OSTSCPINC')) {
            return true;
        }

        // Fallback: detect via request path (works during bootstrap)
        $script = $this->getScriptName();

        return str_contains($script, '/scp/');
    }

    /**
     * Check if current context is client portal.
     *
     * @return bool
     */
    public function isClient(): bool
    {
        // Check constant first (may be set after bootstrap)
        if (defined('OSTCLIENTINC')) {
            return true;
        }

        // Fallback: detect via request path
        $script = $this->getScriptName();

        // Exclude staff panel and API
        if (str_contains($script, '/scp/') || str_contains($script, '/api/')) {
            return false;
        }

        // Must be a PHP file request (not static assets)
        return str_ends_with($script, '.php');
    }

    /**
     * Get the script name from server variables.
     *
     * @return string
     */
    private function getScriptName(): string
    {
        if ($this->serverOverride !== null) {
            $value = $this->serverOverride['SCRIPT_NAME'] ?? '';
            return is_string($value) ? $value : '';
        }

        $value = $_SERVER['SCRIPT_NAME'] ?? '';
        return is_string($value) ? $value : '';
    }

    /**
     * Override server variables (for testing).
     *
     * @param array<string, mixed>|null $server
     * @return void
     */
    public function setServerVariables(?array $server): void
    {
        $this->serverOverride = $server;
    }
}
