<?php

declare(strict_types=1);

namespace CustomCssLoader\Context;

/**
 * Test context detector with configurable forced context.
 *
 * Use this in unit tests to simulate different osTicket contexts
 * without depending on superglobals or osTicket constants.
 *
 * @author Markus Michalski
 * @license GPL-2.0-or-later
 */
class TestContextDetector implements ContextDetectorInterface
{
    public function __construct(
        private ?string $forcedContext = null
    ) {
    }

    /**
     * Detect the current context (returns forced context).
     *
     * @return string|null
     */
    public function detect(): ?string
    {
        return $this->forcedContext;
    }

    /**
     * Check if current context is staff panel.
     *
     * @return bool
     */
    public function isStaff(): bool
    {
        return $this->forcedContext === self::CONTEXT_STAFF;
    }

    /**
     * Check if current context is client portal.
     *
     * @return bool
     */
    public function isClient(): bool
    {
        return $this->forcedContext === self::CONTEXT_CLIENT;
    }

    /**
     * Set the forced context.
     *
     * @param string|null $context 'staff', 'client', or null
     * @return void
     */
    public function setContext(?string $context): void
    {
        $this->forcedContext = $context;
    }
}
