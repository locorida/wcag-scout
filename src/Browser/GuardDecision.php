<?php

declare(strict_types=1);

namespace WcagScout\Browser;

enum GuardDecision
{
    case Allow;
    /** Fail the request; the page sees a network error. */
    case Block;
    /**
     * Cancel a navigation. "Aborted" (unlike other network errors) makes Chromium keep the
     * current document instead of showing an error page.
     */
    case AbortNavigation;

    /** CDP Fetch.failRequest errorReason, or null to let the request continue. */
    public function cdpErrorReason(): ?string
    {
        return match ($this) {
            self::Allow => null,
            self::Block => 'BlockedByClient',
            self::AbortNavigation => 'Aborted',
        };
    }
}
