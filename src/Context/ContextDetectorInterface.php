<?php

declare(strict_types=1);

namespace CustomCssLoader\Context;

/**
 * Interface for detecting the current osTicket context (staff/client).
 *
 * Implementations allow for different detection strategies:
 * - Production: Uses osTicket constants and request paths
 * - Testing: Returns a pre-configured context
 *
 * @author Markus Michalski
 * @license GPL-2.0-or-later
 */
interface ContextDetectorInterface
{
    public const CONTEXT_STAFF = 'staff';
    public const CONTEXT_CLIENT = 'client';

    /**
     * Detect the current context.
     *
     * @return string|null 'staff', 'client', or null (API/CLI/unknown)
     */
    public function detect(): ?string;

    /**
     * Check if current context is staff panel.
     *
     * @return bool
     */
    public function isStaff(): bool;

    /**
     * Check if current context is client portal.
     *
     * @return bool
     */
    public function isClient(): bool;
}
