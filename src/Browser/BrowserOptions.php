<?php

declare(strict_types=1);

namespace WcagScout\Browser;

final readonly class BrowserOptions
{
    public string $chromePath;

    public function __construct(
        ?string $chromePath = null,
        /** Per-page timeout for loading and for each step of the audit. */
        public int $timeoutMs = 30_000,
        /** Extra time after the load event for scripts that render content late. */
        public int $settleMs = 1_000,
        public int $viewportWidth = 1280,
        public int $viewportHeight = 800,
        /**
         * Chromium's own sandbox needs kernel capabilities a default Docker container does
         * not grant; there, the unprivileged container is the isolation boundary instead.
         */
        public bool $sandbox = false,
    ) {
        $fromEnv = getenv('CHROME_PATH');
        $this->chromePath = $chromePath ?? (\is_string($fromEnv) && $fromEnv !== '' ? $fromEnv : 'chromium');
    }
}
