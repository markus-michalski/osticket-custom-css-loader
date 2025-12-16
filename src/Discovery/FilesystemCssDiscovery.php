<?php

declare(strict_types=1);

namespace CustomCssLoader\Discovery;

/**
 * Filesystem-based CSS file discovery.
 *
 * Scans a directory for .css files and categorizes them by filename patterns:
 * - Files containing "staff" → staff context
 * - Files containing "client" → client context
 * - Files without either pattern → ignored
 *
 * Security features:
 * - Path traversal prevention via realpath() validation
 * - Symlink attack prevention
 * - Filename allowlist validation
 *
 * @author Markus Michalski
 * @license GPL-2.0-or-later
 */
class FilesystemCssDiscovery implements CssFileDiscoveryInterface
{
    /**
     * Allowed filename pattern - alphanumeric, hyphens, underscores only.
     * Prevents injection attacks via malicious filenames.
     */
    private const FILENAME_PATTERN = '/^[a-zA-Z0-9][a-zA-Z0-9_-]*\.css$/';

    /**
     * @var array<string, string> Pattern name => regex pattern
     */
    private array $patterns;

    /**
     * @var string|null Cached canonical base directory path
     */
    private ?string $canonicalBaseDir = null;

    /**
     * @param string $baseDirectory Absolute path to scan for CSS files
     * @param array<string, string>|null $patterns Custom patterns (default: staff/client)
     */
    public function __construct(
        private readonly string $baseDirectory,
        ?array $patterns = null
    ) {
        $this->patterns = $patterns ?? [
            'staff' => '/staff/i',
            'client' => '/client/i',
        ];
    }

    /**
     * Discover CSS files categorized by context.
     *
     * Security: Validates all file paths against path traversal attacks
     * and filters filenames through an allowlist pattern.
     *
     * @return array{staff: CssFileInfo[], client: CssFileInfo[]}
     */
    public function discoverFiles(): array
    {
        $rawResult = $this->discoverFilesRaw();

        // Extract only staff and client for interface compliance
        return [
            'staff' => $rawResult['staff'] ?? [],
            'client' => $rawResult['client'] ?? [],
        ];
    }

    /**
     * Discover CSS files with all configured patterns.
     *
     * This method returns files for ALL configured patterns, not just staff/client.
     * Useful for custom pattern configurations.
     *
     * @return array<string, CssFileInfo[]> Keyed by pattern name
     */
    public function discoverFilesRaw(): array
    {
        /** @var array<string, CssFileInfo[]> $result */
        $result = [];

        // Initialize result with all pattern keys
        foreach (array_keys($this->patterns) as $context) {
            $result[$context] = [];
        }

        if (!is_dir($this->baseDirectory)) {
            return $result;
        }

        // Cache the canonical base directory path
        if ($this->canonicalBaseDir === null) {
            $realBaseDir = realpath($this->baseDirectory);
            if ($realBaseDir === false) {
                return $result;
            }
            $this->canonicalBaseDir = $realBaseDir;
        }

        $files = glob($this->baseDirectory . '/*.css');

        if ($files === false || $files === []) {
            return $result;
        }

        foreach ($files as $file) {
            // SECURITY: Validate canonical path to prevent symlink attacks
            $realPath = realpath($file);
            if ($realPath === false) {
                // Broken symlink or non-existent file
                continue;
            }

            // SECURITY: Prevent path traversal via symlinks
            if (!str_starts_with($realPath, $this->canonicalBaseDir . DIRECTORY_SEPARATOR)) {
                error_log(sprintf(
                    '[Custom CSS Loader] Security: Path traversal attempt blocked: %s',
                    $file
                ));
                continue;
            }

            $filename = basename($realPath);

            // SECURITY: Validate filename against allowlist pattern
            if (!$this->isValidFilename($filename)) {
                error_log(sprintf(
                    '[Custom CSS Loader] Security: Invalid filename blocked: %s',
                    $filename
                ));
                continue;
            }

            try {
                $fileInfo = CssFileInfo::fromPath($realPath);
            } catch (\InvalidArgumentException) {
                continue;
            }

            foreach ($this->patterns as $context => $pattern) {
                if (preg_match($pattern, $fileInfo->filename) === 1) {
                    $result[$context][] = $fileInfo;
                    break; // First match wins
                }
            }
        }

        return $result;
    }

    /**
     * Validate filename against security allowlist.
     *
     * Only allows alphanumeric characters, hyphens, and underscores.
     * Must start with alphanumeric and end with .css extension.
     *
     * @param string $filename Filename to validate
     * @return bool True if valid, false otherwise
     */
    public function isValidFilename(string $filename): bool
    {
        return preg_match(self::FILENAME_PATTERN, $filename) === 1;
    }

    /**
     * Get the base directory being scanned.
     *
     * @return string
     */
    public function getBaseDirectory(): string
    {
        return $this->baseDirectory;
    }

    /**
     * Get configured patterns.
     *
     * @return array<string, string>
     */
    public function getPatterns(): array
    {
        return $this->patterns;
    }
}
