<?php

declare(strict_types=1);

namespace CustomCssLoader\Rendering;

use CustomCssLoader\Discovery\CssFileInfo;

/**
 * HTML link tag renderer for CSS files.
 *
 * Generates HTML link tags with:
 * - Cache-busting via ?v={mtime} parameter
 * - XSS prevention via htmlspecialchars
 * - Filename validation as defense-in-depth
 *
 * @author Markus Michalski
 * @license GPL-2.0-or-later
 */
class HtmlCssRenderer implements CssRendererInterface
{
    /**
     * Allowed filename pattern - alphanumeric, hyphens, underscores only.
     * Defense-in-depth: also validated in FilesystemCssDiscovery.
     */
    private const FILENAME_PATTERN = '/^[a-zA-Z0-9][a-zA-Z0-9_-]*\.css$/';

    /**
     * @param string $baseUrl Base URL path (e.g., "/assets/custom/css")
     */
    public function __construct(
        private readonly string $baseUrl
    ) {
    }

    /**
     * Render a CSS link tag for a file.
     *
     * Security: Validates filename before rendering as defense-in-depth.
     *
     * @param CssFileInfo $fileInfo CSS file information
     * @return string HTML link tag, or empty string if filename is invalid
     */
    public function render(CssFileInfo $fileInfo): string
    {
        // Defense-in-depth: validate filename even though discovery should have done this
        if (!$this->isValidFilename($fileInfo->filename)) {
            error_log(sprintf(
                '[Custom CSS Loader] Security: Invalid filename blocked in renderer: %s',
                $fileInfo->filename
            ));
            return '';
        }

        $url = $this->buildUrl($fileInfo);
        $escapedUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

        return sprintf('<link rel="stylesheet" href="%s">', $escapedUrl);
    }

    /**
     * Validate filename against security allowlist.
     *
     * @param string $filename Filename to validate
     * @return bool True if valid, false otherwise
     */
    public function isValidFilename(string $filename): bool
    {
        return preg_match(self::FILENAME_PATTERN, $filename) === 1;
    }

    /**
     * Render multiple CSS link tags.
     *
     * Filters out any files with invalid filenames.
     *
     * @param CssFileInfo[] $files Array of CSS file info objects
     * @return string[] Array of HTML link tags (empty strings filtered out)
     */
    public function renderAll(array $files): array
    {
        return array_values(array_filter(
            array_map(
                fn(CssFileInfo $file): string => $this->render($file),
                $files
            ),
            fn(string $link): bool => $link !== ''
        ));
    }

    /**
     * Build the URL for a CSS file with cache-busting parameter.
     *
     * @param CssFileInfo $fileInfo CSS file information
     * @return string URL with cache-busting parameter
     */
    private function buildUrl(CssFileInfo $fileInfo): string
    {
        $url = rtrim($this->baseUrl, '/') . '/' . $fileInfo->filename;

        // Add cache-busting parameter
        if ($fileInfo->mtime > 0) {
            $url .= '?v=' . $fileInfo->mtime;
        }

        return $url;
    }

    /**
     * Get the base URL.
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
}
