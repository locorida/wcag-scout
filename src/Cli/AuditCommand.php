<?php

declare(strict_types=1);

namespace WcagScout\Cli;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;
use WcagScout\Audit\AuditRunner;
use WcagScout\Audit\BrowserPageAuditor;
use WcagScout\Audit\ScreenshotWriter;
use WcagScout\Browser\BrowserOptions;
use WcagScout\Browser\ChromeBrowser;
use WcagScout\Crawl\Crawler;
use WcagScout\Crawl\RobotsPolicy;
use WcagScout\Crawl\Throttle;
use WcagScout\Crawl\UserAgent;
use WcagScout\Engine\Axe\AxeEngine;
use WcagScout\Input\SitemapSource;
use WcagScout\Input\UrlListSource;
use WcagScout\Input\UrlSource;
use WcagScout\Model\AuditRun;
use WcagScout\Report\ReportOptions;

#[AsCommand(name: 'audit', description: 'Audit pages of a website and write accessibility reports.')]
final class AuditCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('urls', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'URLs to audit')
            ->addOption('urls-file', 'f', InputOption::VALUE_REQUIRED, 'Text file with one URL per line ("#" starts a comment)')
            ->addOption('sitemap', 's', InputOption::VALUE_REQUIRED, 'URL of a sitemap or sitemap index')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Directory for the reports', 'wcag-scout-report')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Comma-separated: html, pdf, json, junit', implode(',', AuditConfig::DEFAULT_FORMATS))
            ->addOption('lang', 'l', InputOption::VALUE_REQUIRED, 'Report language: en or de', 'en')
            ->addOption('level', null, InputOption::VALUE_REQUIRED, 'Highest WCAG level to test: A, AA or AAA', 'AA')
            ->addOption('no-best-practices', null, InputOption::VALUE_NONE, 'Only test WCAG criteria, skip best-practice rules')
            ->addOption('en301549', null, InputOption::VALUE_REQUIRED, 'EN 301 549 version for clause references: 3.2.1 or 4.1.1', '3.2.1')
            ->addOption('max-pages', 'm', InputOption::VALUE_REQUIRED, 'Maximum number of pages to load', '50')
            ->addOption('delay', 'd', InputOption::VALUE_REQUIRED, 'Pause between requests to the same site, in seconds', '1')
            ->addOption('follow-links', null, InputOption::VALUE_NONE, 'Also audit links found on the pages (same site only)')
            ->addOption('max-depth', null, InputOption::VALUE_REQUIRED, 'Link depth for --follow-links', '2')
            ->addOption('contact-url', null, InputOption::VALUE_REQUIRED, 'Contact URL sent in the user agent', UserAgent::DEFAULT_CONTACT)
            ->addOption('timeout', 't', InputOption::VALUE_REQUIRED, 'Timeout per page, in seconds', '30')
            ->addOption('screenshots', null, InputOption::VALUE_REQUIRED, 'Element screenshots per rule and page (0 = none)', '3')
            ->addOption('fail-on', null, InputOption::VALUE_REQUIRED, 'Lowest severity that fails the run: critical, serious, moderate, minor or never', 'minor')
            ->addOption('chrome-path', null, InputOption::VALUE_REQUIRED, 'Chromium/Chrome binary (default: $CHROME_PATH or "chromium")')
            ->setHelp(<<<'HELP'
                Examples:
                  <info>wcag-scout audit https://example.com/ https://example.com/contact</info>
                  <info>wcag-scout audit --sitemap https://example.com/sitemap.xml --format html,pdf,json --lang de</info>
                  <info>wcag-scout audit https://example.com/ --follow-links --max-pages 20 --baseline baseline.json</info>

                Exit codes: 0 no (new) findings at or above --fail-on, 1 such findings, 2 invalid usage, 3 error.

                The crawler honours robots.txt, waits --delay seconds between requests, never
                submits forms and blocks every request that is not GET/HEAD/OPTIONS.
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        try {
            $config = AuditConfig::fromInput($input);
            $source = $this->source($config, $userAgent = new UserAgent($config->contactUrl), $throttle = new Throttle($config->crawl->delaySeconds));
        } catch (InvalidArgumentException $e) {
            $io->getErrorStyle()->error($e->getMessage());

            return ExitCode::USAGE;
        }

        $browser = new ChromeBrowser($userAgent, new BrowserOptions($config->chromePath, $config->timeoutSeconds * 1000));
        try {
            $engine = new AxeEngine($config->audit);
            $auditor = new BrowserPageAuditor(
                $browser,
                [$engine],
                $config->screenshotsPerRule > 0 ? new ScreenshotWriter($config->outputDirectory, $config->screenshotsPerRule) : null,
                $config->crawl->followLinks,
            );
            $crawler = new Crawler(
                new RobotsPolicy(HttpClient::create(), $userAgent, $throttle),
                $throttle,
                $auditor,
                $config->crawl,
                new ConsoleProgress($errorOutput),
            );

            $run = new AuditRunner($crawler, [$engine])->run($source, $config->scopeOrigins(), $config->record());

            $options = new ReportOptions(
                $config->audit->language,
                $config->audit->level,
                $config->en301549,
                null,
                $config->failOn,
            );
            $files = Reports::writer($browser)->write($run, $options, $config->outputDirectory, $config->formats);
        } catch (InvalidArgumentException $e) {
            $io->getErrorStyle()->error($e->getMessage());

            return ExitCode::USAGE;
        } catch (RuntimeException $e) {
            $io->getErrorStyle()->error($e->getMessage());

            return ExitCode::ERROR;
        } finally {
            $browser->close();
        }

        return $this->summarise($io, $run, $options, $files);
    }

    private function source(AuditConfig $config, UserAgent $userAgent, Throttle $throttle): UrlSource
    {
        if ($config->sitemap !== null) {
            return new SitemapSource($config->sitemap, HttpClient::create(), $userAgent, $throttle);
        }
        if ($config->urlsFile !== null) {
            return UrlListSource::fromFile($config->urlsFile);
        }

        return UrlListSource::fromStrings(array_map(strval(...), $config->urls));
    }

    /** @param list<string> $files */
    private function summarise(SymfonyStyle $io, AuditRun $run, ReportOptions $options, array $files): int
    {
        $failing = \count(array_filter($run->findings(), $options->isFailing(...)));

        $rows = [];
        foreach ($run->countBySeverity() as $severity => $count) {
            $rows[] = [$severity, $count];
        }
        $io->newLine();
        $io->table(['Severity', 'Findings'], $rows);
        $io->listing($files);

        if ($failing > 0) {
            $io->getErrorStyle()->warning(\sprintf(
                '%d finding(s) fail the run%s.',
                $failing,
                $options->hasBaseline() ? ' (new since the baseline)' : '',
            ));

            return ExitCode::FINDINGS;
        }
        $io->success('No findings fail the run. Automated tests find only part of all problems: see the manual checks in the report.');

        return ExitCode::OK;
    }
}
