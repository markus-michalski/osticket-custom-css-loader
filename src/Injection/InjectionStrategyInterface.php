<?php

declare(strict_types=1);

namespace CustomCssLoader\Injection;

/**
 * Interface for CSS injection strategies.
 *
 * Different injection strategies for different osTicket contexts:
 * - Output Buffer: Injects before </head> via ob_start callback
 * - osTicket Header: Uses $ost->addExtraHeader()
 *
 * @author Markus Michalski
 * @license GPL-2.0-or-later
 */
interface InjectionStrategyInterface
{
    /**
     * Inject CSS link tags into the buffer/output.
     *
     * @param string $buffer The current output buffer (for output buffer strategy)
     * @param string[] $cssLinks Array of HTML link tags to inject
     * @return string Modified buffer (or original for header strategy)
     */
    public function inject(string $buffer, array $cssLinks): string;
}
