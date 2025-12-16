<?php

declare(strict_types=1);

namespace CustomCssLoader\Discovery;

/**
 * Interface for discovering CSS files in a directory.
 *
 * Implementations scan directories for CSS files and categorize them
 * by context (staff/client) based on filename patterns.
 *
 * @author Markus Michalski
 * @license GPL-2.0-or-later
 */
interface CssFileDiscoveryInterface
{
    /**
     * Discover CSS files categorized by context.
     *
     * @return array{staff: CssFileInfo[], client: CssFileInfo[]}
     */
    public function discoverFiles(): array;

    /**
     * Get the base directory being scanned.
     *
     * @return string
     */
    public function getBaseDirectory(): string;
}
