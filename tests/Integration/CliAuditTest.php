<?php

declare(strict_types=1);

namespace WcagScout\Tests\Integration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use WcagScout\Browser\BrowserOptions;
use WcagScout\Browser\ChromeBrowser;
use WcagScout\Cli\ExitCode;
use WcagScout\Crawl\UserAgent;
use WcagScout\Engine\AuditOptions;
use WcagScout\Engine\Axe\AxeEngine;
use WcagScout\Model\Finding;
use WcagScout\Model\Url;
use WcagScout\Report\Json\RunDeserializer;
use WcagScout\Report\Json\RunSerializer;
use WcagScout\Standards\Level;
use WcagScout\Tests\Support\FixtureServer;
use WcagScout\Tests\Support\TempDirectory;

/**
 * The real command line, end to end, against the fixture site.
 */
#[Group('integration')]
final class CliAuditTest extends TestCase
{
    private static FixtureServer $site;
    private static string $output;
    private static Process $process;

    public static function setUpBeforeClass(): void
    {
        $chrome = getenv('CHROME_PATH');
        if (!\is_string($chrome) || !is_executable($chrome)) {
            self::markTestSkipped('Chromium not found (set CHROME_PATH). Run inside the dev image.');
        }
        self::$site = new FixtureServer();
        self::$output = TempDirectory::path('wcag-scout-cli');

        self::$process = new Process([
            PHP_BINARY, \dirname(__DIR__, 2) . '/bin/wcag-scout', 'audit',
            '--sitemap', self::$site->url('/sitemap_index.xml'),
            '--format', 'html,pdf,json,junit',
            '--lang', 'de',
            '--delay', '0',
            '--output', self::$output,
        ], timeout: 300);
        self::$process->run();
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$site)) {
            self::$site->stop();
            TempDirectory::remove(self::$output);
        }
    }

    public function testFailsWithFindingsAndWritesAllFormats(): void
    {
        self::assertSame(ExitCode::FINDINGS, self::$process->getExitCode(), self::$process->getErrorOutput() . self::$process->getOutput());
        foreach (['report.html', 'report.pdf', 'report.json', 'junit.xml'] as $file) {
            self::assertFileExists(self::$output . '/' . $file);
        }
        self::assertStringStartsWith('%PDF-', (string) file_get_contents(self::$output . '/report.pdf'));
        self::assertStringContainsString('[skip]', self::$process->getErrorOutput(), 'robots.txt exclusion is shown');
    }

    public function testJsonReportIsReadableAndComplete(): void
    {
        $json = (string) file_get_contents(self::$output . '/report.json');
        $run = new RunDeserializer()->deserialize($json);

        self::assertSame($json, new RunSerializer()->serialize($run), 'what we write, we can read back unchanged');
        self::assertCount(9, $run->findings());
        self::assertSame('de', $run->options['language']);
        self::assertArrayHasKey('image-alt', $run->rules);
        foreach ($run->findings() as $finding) {
            self::assertNotNull($finding->screenshot, $finding->ruleId . ' has a screenshot');
            self::assertFileExists(self::$output . '/' . $finding->screenshot);
        }
    }

    /**
     * The report must itself be accessible: axe-core (including best practices, up to AAA)
     * finds nothing in it.
     */
    public function testHtmlReportPassesItsOwnAudit(): void
    {
        $server = new FixtureServer(self::$output);
        $browser = new ChromeBrowser(new UserAgent(), new BrowserOptions(settleMs: 200));
        try {
            $engine = new AxeEngine(new AuditOptions(Level::AAA, bestPractices: true));
            $page = $browser->open(Url::parse($server->url('/report.html')));
            $findings = $engine->run($page, Url::parse($server->url('/report.html')));
            $page->close();
        } finally {
            $browser->close();
            $server->stop();
        }

        // AAA contrast (7:1) is not a goal for the report; everything else is.
        $findings = array_filter($findings, static fn(Finding $f): bool => $f->ruleId !== 'color-contrast-enhanced');
        self::assertSame([], array_map(static fn(Finding $f): string => $f->ruleId . ' ' . $f->selector, array_values($findings)));
    }
}
