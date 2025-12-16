<?php

declare(strict_types=1);

namespace CustomCssLoader\Discovery;

/**
 * Immutable value object representing a discovered CSS file.
 *
 * Note: Using readonly properties instead of readonly class for PHP 8.1 compatibility.
 *
 * @author Markus Michalski
 * @license GPL-2.0-or-later
 */
class CssFileInfo
{
    public function __construct(
        public readonly string $path,
        public readonly string $filename,
        public readonly int $mtime
    ) {
    }

    /**
     * Create a CssFileInfo from a file path.
     *
     * @param string $path Absolute path to the CSS file
     * @return self
     * @throws \InvalidArgumentException If the file does not exist
     */
    public static function fromPath(string $path): self
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException(
                sprintf('CSS file does not exist: %s', $path)
            );
        }

        return new self(
            path: $path,
            filename: basename($path),
            mtime: filemtime($path) ?: 0
        );
    }

    /**
     * Create a CssFileInfo from an array (legacy compatibility).
     *
     * @param array{path: string, filename: string, mtime: int} $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            path: $data['path'],
            filename: $data['filename'],
            mtime: $data['mtime']
        );
    }

    /**
     * Convert to array (legacy compatibility).
     *
     * @return array{path: string, filename: string, mtime: int}
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'filename' => $this->filename,
            'mtime' => $this->mtime,
        ];
    }
}
