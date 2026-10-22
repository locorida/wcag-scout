<?php

declare(strict_types=1);

namespace WcagScout\Crawl;

use DateTimeImmutable;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\ClockInterface;
use WcagScout\Model\Url;

/**
 * Keeps a minimum pause between requests to the same origin.
 */
final class Throttle
{
    /** @var array<string, DateTimeImmutable> origin => time of the last request */
    private array $lastRequest = [];

    /** @var array<string, float> origin => delay required by robots.txt */
    private array $originDelay = [];

    public function __construct(
        private readonly float $delaySeconds,
        private readonly ClockInterface $clock = new Clock(),
    ) {}

    /** robots.txt may ask for a longer pause than configured; never a shorter one. */
    public function requireDelay(Url $url, float $seconds): void
    {
        $this->originDelay[$url->origin()] = max($seconds, $this->originDelay[$url->origin()] ?? 0.0);
    }

    public function delayFor(Url $url): float
    {
        return max($this->delaySeconds, $this->originDelay[$url->origin()] ?? 0.0);
    }

    /** Blocks until the next request to $url's origin is allowed, then records it. */
    public function wait(Url $url): void
    {
        $origin = $url->origin();
        if (isset($this->lastRequest[$origin])) {
            $elapsed = (float) $this->clock->now()->format('U.u') - (float) $this->lastRequest[$origin]->format('U.u');
            $remaining = $this->delayFor($url) - $elapsed;
            if ($remaining > 0) {
                $this->clock->sleep($remaining);
            }
        }
        $this->lastRequest[$origin] = $this->clock->now();
    }
}
