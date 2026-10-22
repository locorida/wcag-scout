<?php

declare(strict_types=1);

namespace WcagScout\Browser;

/**
 * A loaded, frozen page, as seen by audit engines. Hides the browser library.
 */
interface BrowserPage
{
    public function info(): NavigationInfo;

    /**
     * Evaluates a JavaScript expression in the page and returns its JSON-compatible value.
     * Promises are awaited.
     *
     * @throws BrowserException
     */
    public function evaluate(string $expression, int $timeoutMs = 30_000): mixed;

    /**
     * Saves a PNG of the element matched by $selector, highlighted, with some context.
     *
     * @return bool false if the element cannot be found or has no visible size
     */
    public function screenshotElement(string $selector, string $path): bool;

    /** @return list<string> absolute http(s) URLs of the links on the page */
    public function links(): array;

    public function close(): void;
}
