<?php

declare(strict_types=1);

namespace CustomCssLoader\Injection;

/**
 * Output buffer-based CSS injection strategy.
 *
 * Injects CSS link tags into the HTML buffer before the </head> tag.
 * This is the primary injection method used during osTicket bootstrap
 * when $ost is not yet available.
 *
 * @author Markus Michalski
 * @license GPL-2.0-or-later
 */
class OutputBufferInjectionStrategy implements InjectionStrategyInterface
{
    private const COMMENT_START = '<!-- Custom CSS Loader Plugin -->';
    private const COMMENT_END = '<!-- /Custom CSS Loader Plugin -->';

    /**
     * Inject CSS link tags before </head>.
     *
     * Security: Only injects before the FIRST </head> tag to prevent
     * multiple injections in malformed HTML.
     *
     * @param string $buffer The current output buffer
     * @param string[] $cssLinks Array of HTML link tags to inject
     * @return string Modified buffer with CSS injected
     */
    public function inject(string $buffer, array $cssLinks): string
    {
        // Nothing to inject
        if ($cssLinks === []) {
            return $buffer;
        }

        // Find first </head> tag position (case-insensitive)
        $headPos = stripos($buffer, '</head>');
        if ($headPos === false) {
            return $buffer;
        }

        // Build CSS injection block
        $injection = $this->buildInjectionBlock($cssLinks);

        // SECURITY: Inject only before the FIRST </head> using position-based replacement
        return substr($buffer, 0, $headPos) . $injection . substr($buffer, $headPos);
    }

    /**
     * Build the CSS injection block with comments.
     *
     * @param string[] $cssLinks Array of HTML link tags
     * @return string Formatted injection block
     */
    private function buildInjectionBlock(array $cssLinks): string
    {
        $lines = [
            '',
            '    ' . self::COMMENT_START,
        ];

        foreach ($cssLinks as $link) {
            $lines[] = '    ' . $link;
        }

        $lines[] = '    ' . self::COMMENT_END;
        $lines[] = '    ';

        return implode("\n", $lines);
    }
}
