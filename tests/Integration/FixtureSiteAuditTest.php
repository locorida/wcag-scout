<?php

declare(strict_types=1);

namespace WcagScout\Tests\Integration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;
use WcagScout\Audit\BrowserPageAuditor;
use WcagScout\Audit\ScreenshotWriter;
use WcagScout\Browser\BrowserOptions;
use WcagScout\Browser\ChromeBrowser;
use WcagScout\Crawl\Crawler;
use WcagScout\Crawl\CrawlOptions;
use WcagScout\Crawl\RobotsPolicy;
use WcagScout\Crawl\Throttle;
use WcagScout\Crawl\UserAgent;
use WcagScout\Engine\AuditOptions;
use WcagScout\Engine\Axe\AxeEngine;
use WcagScout\Input\SitemapSource;
use WcagScout\Input\UrlListSource;
use WcagScout\Model\Finding;
use WcagScout\Model\PageResult;
use WcagScout\Model\Url;
use WcagScout\Tests\Support\FixtureServer;
use WcagScout\Tests\Support\TempDirectory;

/**
 * End-to-end: real Chromium, real axe-core, against the fixture pages with known errors.
 * Needs Chromium; runs in the dev Docker image (composer test:integration).
 */
#[Group('integration')]
final class FixtureSiteAuditTest extends TestCase
{
    private static FixtureServer $server;
    private static ChromeBrowser $browser;
    private string $reportDir;

    public static function setUpBeforeClass(): void
    {
        $chrome = getenv('CHROME_PATH');
        if (!\is_string($chrome) || !is_executable($chrome)) {
            self::markTestSkipped('Chromium not found (set CHROME_PATH). Run inside the dev image.');
        }
        self::$server = new FixtureServer();
        self::$browser = new ChromeBrowser(new UserAgent(), new BrowserOptions(settleMs: 300));
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$browser)) {
            self::$browser->close();
        }
        if (isset(self::$server)) {
            self::$server->stop();
        }
    }

    protected function setUp(): void
    {
        $this->reportDir = TempDirectory::path('wcag-scout-it');
    }

    protected function tearDown(): void
    {
        TempDirectory::remove($this->reportDir);
    }

    public function testAuditsSitemapAndFindsExactlyTheBuiltInErrors(): void
    {
        $http = HttpClient::create();
        $throttle = new Throttle(0.0);
        $source = new SitemapSource(Url::parse(self::$server->url('/sitemap_index.xml')), $http, new UserAgent(), $throttle);

        $results = $this->crawler($throttle)->crawl($source);
        $byPath = [];
        foreach ($results as $result) {
            $byPath[$result->url->path] = $result;
        }

        self::assertSame(
            ['/', '/missing-alt.html', '/low-contrast.html', '/form.html', '/no-lang.html', '/private/secret.html', '/does-not-exist.html'],
            array_keys($byPath),
        );

        self::assertSame([], self::rules($byPath['/']), 'the accessible start page has no findings');
        self::assertSame(['image-alt' => 3, 'link-name' => 1], self::rules($byPath['/missing-alt.html']));
        self::assertSame(['color-contrast' => 1], self::rules($byPath['/low-contrast.html']));
        self::assertSame(['button-name' => 1, 'label' => 1], self::rules($byPath['/form.html']));
        self::assertSame(['heading-order' => 1, 'html-has-lang' => 1], self::rules($byPath['/no-lang.html']));

        self::assertSame('skipped', $byPath['/private/secret.html']->status->value);
        self::assertSame(PageResult::SKIPPED_ROBOTS_TXT, $byPath['/private/secret.html']->reason);
        self::assertNotContains('GET /private/secret.html', self::$server->requestLog(), 'disallowed page must never be requested');

        self::assertSame('failed', $byPath['/does-not-exist.html']->status->value);
        self::assertSame(404, $byPath['/does-not-exist.html']->httpStatus);

        self::assertSame('Fixture – low contrast', $byPath['/low-contrast.html']->title);
    }

    public function testMapsFindingsToWcagAndTakesScreenshots(): void
    {
        $results = $this->crawler(new Throttle(0.0))->crawl(UrlListSource::fromStrings([self::$server->url('/low-contrast.html')]));
        $finding = $results[0]->findings[0];

        self::assertSame('color-contrast', $finding->ruleId);
        self::assertSame('serious', $finding->severity->value);
        self::assertSame('1.4.3', $finding->criteria[0]->number);
        self::assertSame('9.1.4.3', $finding->criteria[0]->en301549Clause());
        self::assertSame('#faint-text', $finding->selector);
        self::assertStringContainsString('This grey text is hard to read.', $finding->html);
        self::assertNotNull($finding->screenshot);

        $png = $this->reportDir . '/' . $finding->screenshot;
        self::assertFileExists($png);
        $size = getimagesize($png);
        self::assertIsArray($size);
        // The paragraph spans the page (1264 px), so the clip is capped at the maximum width;
        // it is a single line high, so the minimum height applies.
        self::assertSame([1200, 270], [$size[0], $size[1]]);
    }

    public function testNeverSendsDataToTheSite(): void
    {
        $results = $this->crawler(new Throttle(0.0))->crawl(UrlListSource::fromStrings([self::$server->url('/auto-submit.html')]));
        usleep(500_000);

        self::assertSame('audited', $results[0]->status->value, 'the page stays loaded although it submits a form');
        self::assertSame([], self::$server->sinkRequests(), 'no POST/PUT/beacon/form submission may reach the server');
    }

    public function testFollowsLinksOnlyWithinTheSite(): void
    {
        $results = $this->crawler(new Throttle(0.0), new CrawlOptions(maxPages: 20, delaySeconds: 0, followLinks: true, maxDepth: 1))
            ->crawl(UrlListSource::fromStrings([self::$server->url('/')]));

        $urls = array_map(static fn(PageResult $r): string => (string) $r->url, $results);
        self::assertContains(self::$server->url('/form.html'), $urls);
        self::assertNotContains('https://example.com/', $urls, 'external links are never followed');
        self::assertCount(1, array_filter($urls, static fn(string $u): bool => $u === self::$server->url('/')), 'no URL twice');
    }

    public function testGermanEngineMessages(): void
    {
        $engine = new AxeEngine(new AuditOptions(language: 'de'));
        $auditor = new BrowserPageAuditor(self::$browser, [$engine]);

        $finding = $auditor->audit(Url::parse(self::$server->url('/missing-alt.html')))->result->findings[0];

        self::assertStringContainsString('Element', $finding->summary);
        self::assertStringNotContainsString('Fix any of the following', $finding->summary);
        self::assertStringContainsStringIgnoringCase('alternativ', $engine->rules()['image-alt']->help);
    }

    private function crawler(Throttle $throttle, CrawlOptions $options = new CrawlOptions(delaySeconds: 0)): Crawler
    {
        $userAgent = new UserAgent();
        $auditor = new BrowserPageAuditor(
            self::$browser,
            [new AxeEngine(new AuditOptions())],
            new ScreenshotWriter($this->reportDir),
            $options->followLinks,
        );

        return new Crawler(new RobotsPolicy(HttpClient::create(), $userAgent, $throttle), $throttle, $auditor, $options);
    }

    /** @return array<string, int> rule id => number of findings, sorted by rule id */
    private static function rules(PageResult $page): array
    {
        $counts = array_count_values(array_map(static fn(Finding $f): string => $f->ruleId, $page->findings));
        ksort($counts);

        return $counts;
    }
}
