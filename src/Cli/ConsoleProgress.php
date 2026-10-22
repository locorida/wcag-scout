<?php

declare(strict_types=1);

namespace WcagScout\Cli;

use Symfony\Component\Console\Output\OutputInterface;
use WcagScout\Crawl\CrawlObserver;
use WcagScout\Model\PageResult;
use WcagScout\Model\PageStatus;
use WcagScout\Model\Url;

/**
 * One line per page on stderr, so stdout stays clean for piping.
 */
final readonly class ConsoleProgress implements CrawlObserver
{
    public function __construct(private OutputInterface $output) {}

    public function pageStarted(Url $url, int $number, int $limit): void
    {
        $this->output->write(\sprintf('<comment>[%d/%d]</comment> %s ', $number, $limit, $url));
    }

    public function pageFinished(PageResult $result): void
    {
        $this->output->writeln(match ($result->status) {
            PageStatus::Audited => \sprintf('<info>%d finding(s)</info> (%.1fs)', \count($result->findings), $result->durationMs / 1000),
            PageStatus::Skipped => \sprintf('<comment>[skip]</comment> %s <comment>%s</comment>', $result->url, $result->reason),
            PageStatus::Failed => '<error>failed: ' . $result->reason . '</error>',
        });
    }
}
