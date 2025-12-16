<?php

declare(strict_types=1);

namespace CustomCssLoader\Rendering;

use CustomCssLoader\Discovery\CssFileInfo;

/**
 * Interface for rendering CSS link tags.
 *
 * Implementations convert CssFileInfo objects to HTML link tags
 * with proper escaping and cache-busting parameters.
 *
 * @author Markus Michalski
 * @license GPL-2.0-or-later
 */
interface CssRendererInterface
{
    /**
     * Render a CSS link tag for a file.
     *
     * @param CssFileInfo $fileInfo CSS file information
     * @return string HTML link tag
     */
    public function render(CssFileInfo $fileInfo): string;

    /**
     * Render multiple CSS link tags.
     *
     * @param CssFileInfo[] $files Array of CSS file info objects
     * @return string[] Array of HTML link tags
     */
    public function renderAll(array $files): array;
}
