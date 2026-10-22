<?php

declare(strict_types=1);

namespace WcagScout\Browser;

/**
 * Decides for every request the browser makes whether it may go out.
 *
 * wcag-scout only *reads* a site:
 * - Requests that can change state on the server (POST, PUT, PATCH, DELETE, …) are blocked,
 *   whether they come from forms, fetch(), XMLHttpRequest or navigator.sendBeacon().
 * - Once the page has loaded it is "frozen": any further navigation of the main frame
 *   (form submission, script redirect, meta refresh) is aborted, so the page that is
 *   audited stays the page that was requested.
 */
final readonly class RequestGuard
{
    private const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function decide(string $method, string $resourceType, bool $mainFrame, bool $frozen): GuardDecision
    {
        if ($frozen && $mainFrame && $resourceType === 'Document') {
            return GuardDecision::AbortNavigation;
        }
        if (!\in_array(strtoupper($method), self::SAFE_METHODS, true)) {
            return GuardDecision::Block;
        }

        return GuardDecision::Allow;
    }
}
