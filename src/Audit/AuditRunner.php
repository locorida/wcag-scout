<?php

declare(strict_types=1);

namespace WcagScout\Audit;

use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\ClockInterface;
use WcagScout\Cli\Application;
use WcagScout\Crawl\Crawler;
use WcagScout\Engine\AuditEngine;
use WcagScout\Input\UrlSource;
use WcagScout\Model\AuditRun;

/**
 * Runs a crawl and assembles the AuditRun: timestamps, engine versions, rule metadata.
 */
final readonly class AuditRunner
{
    /** @param list<AuditEngine> $engines the same engine instances the crawler's auditor uses */
    public function __construct(
        private Crawler $crawler,
        private array $engines,
        private ClockInterface $clock = new Clock(),
    ) {}

    /**
     * @param list<string> $scopeOrigins
     * @param array<string, scalar|list<string>|null> $options recorded in the report
     */
    public function run(UrlSource $source, array $scopeOrigins, array $options): AuditRun
    {
        $startedAt = $this->clock->now();
        $pages = $this->crawler->crawl($source, $scopeOrigins);
        $finishedAt = $this->clock->now();

        $engines = [];
        $rules = [];
        foreach ($this->engines as $engine) {
            $engines[$engine->name()] = $engine->version();
            $rules += $engine->rules();
        }

        return new AuditRun(Application::VERSION, $startedAt, $finishedAt, $engines, $options, $pages, $rules);
    }
}
