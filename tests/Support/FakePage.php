<?php

declare(strict_types=1);

namespace WcagScout\Tests\Support;

use WcagScout\Browser\BrowserPage;
use WcagScout\Browser\NavigationInfo;

final class FakePage implements BrowserPage
{
    public bool $closed = false;

    /** @param list<string> $links */
    public function __construct(private readonly NavigationInfo $info, private readonly array $links = []) {}

    public function info(): NavigationInfo
    {
        return $this->info;
    }

    public function evaluate(string $expression, int $timeoutMs = 30_000): mixed
    {
        return null;
    }

    public function screenshotElement(string $selector, string $path): bool
    {
        return file_put_contents($path, 'png') !== false;
    }

    public function links(): array
    {
        return $this->links;
    }

    public function close(): void
    {
        $this->closed = true;
    }
}
