<?php

declare(strict_types=1);

namespace WcagScout\Tests\Unit\Crawl;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use WcagScout\Crawl\Crawler;
use WcagScout\Crawl\CrawlOptions;
use WcagScout\Crawl\PageAuditor;
use WcagScout\Crawl\PageVisit;
use WcagScout\Crawl\RobotsPolicy;
use WcagScout\Crawl\Throttle;
use WcagScout\Crawl\UserAgent;
use WcagScout\Input\UrlListSource;
use WcagScout\Model\PageResult;
use WcagScout\Model\PageStatus;
use WcagScout\Model\Url;

final class CrawlerTest extends TestCase
{
    private MockClock $clock;

    /** @var list<string> */
    private array $audited = [];

    /** @var list<string> */
    private array $auditTimes = [];

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-01-01 00:00:00');
    }

    public function testSkipsDisallowedAndForeignUrlsAndAuditsEachUrlOnce(): void
    {
        $results = $this->crawler("User-agent: *\nDisallow: /private")->crawl(UrlListSource::fromStrings([
            'https://example.com/',
            'https://example.com/private/x',
            'https://other.example/',
            'https://example.com/#same-page',
            'https://example.com/about',
        ]));

        self::assertSame(['https://example.com/', 'https://example.com/about'], $this->audited);
        self::assertSame([
            ['https://example.com/', 'audited', null],
            ['https://example.com/private/x', 'skipped', PageResult::SKIPPED_ROBOTS_TXT],
            ['https://other.example/', 'skipped', PageResult::SKIPPED_OUT_OF_SCOPE],
            ['https://example.com/about', 'audited', null],
        ], array_map(static fn(PageResult $r): array => [(string) $r->url, $r->status->value, $r->reason], $results));
    }

    public function testExplicitScopeAllowsSeveralSites(): void
    {
        $this->crawler()->crawl(
            UrlListSource::fromStrings(['https://example.com/', 'https://other.example/']),
            ['https://example.com', 'https://other.example'],
        );

        self::assertSame(['https://example.com/', 'https://other.example/'], $this->audited);
    }

    public function testStopsAtPageLimitWithoutCountingSkippedPages(): void
    {
        $results = $this->crawler("User-agent: *\nDisallow: /skip", new CrawlOptions(maxPages: 2, delaySeconds: 0))->crawl(
            UrlListSource::fromStrings(['https://example.com/skip1', 'https://example.com/1', 'https://example.com/skip2', 'https://example.com/2', 'https://example.com/3']),
        );

        self::assertSame(['https://example.com/1', 'https://example.com/2'], $this->audited);
        self::assertCount(4, $results);
    }

    public function testWaitsBetweenPagesAndHonoursLongerCrawlDelay(): void
    {
        $this->crawler("User-agent: *\nCrawl-delay: 3", new CrawlOptions(delaySeconds: 1.0))->crawl(
            UrlListSource::fromStrings(['https://example.com/1', 'https://example.com/2', 'https://example.com/3']),
        );

        // robots.txt was fetched at 00:00:00, so the first page waits for the crawl delay as well.
        self::assertSame(['00:00:03', '00:00:06', '00:00:09'], $this->auditTimes);
    }

    public function testFollowsSameSiteLinksBreadthFirstUpToMaxDepth(): void
    {
        $links = [
            'https://example.com/' => ['/a', '/b', 'https://other.example/x'],
            'https://example.com/a' => ['/a/deep', '/b'],
            'https://example.com/b' => ['/'],
            'https://example.com/a/deep' => ['/a/deeper'],
        ];

        $this->crawler(options: new CrawlOptions(delaySeconds: 0, followLinks: true, maxDepth: 2), links: $links)
            ->crawl(UrlListSource::fromStrings(['https://example.com/']));

        self::assertSame(['https://example.com/', 'https://example.com/a', 'https://example.com/b', 'https://example.com/a/deep'], $this->audited);
    }

    public function testDoesNotFollowLinksByDefault(): void
    {
        $this->crawler(links: ['https://example.com/' => ['/a']])->crawl(UrlListSource::fromStrings(['https://example.com/']));

        self::assertSame(['https://example.com/'], $this->audited);
    }

    public function testRecordsAuditorFailuresAndContinues(): void
    {
        $results = $this->crawler(failOn: 'https://example.com/broken')->crawl(
            UrlListSource::fromStrings(['https://example.com/broken', 'https://example.com/ok']),
        );

        self::assertSame(PageStatus::Failed, $results[0]->status);
        self::assertSame('browser crashed', $results[0]->reason);
        self::assertSame(PageStatus::Audited, $results[1]->status);
    }

    /** @param array<string, list<string>> $links */
    private function crawler(
        string $robots = '',
        CrawlOptions $options = new CrawlOptions(delaySeconds: 0),
        array $links = [],
        ?string $failOn = null,
    ): Crawler {
        $throttle = new Throttle($options->delaySeconds, $this->clock);
        // A factory, so every site gets its own robots.txt response.
        $http = new MockHttpClient(static fn(): MockResponse => new MockResponse($robots));
        $robotsPolicy = new RobotsPolicy($http, new UserAgent(), $throttle);

        $auditor = new class ($this, $links, $failOn) implements PageAuditor {
            /** @param array<string, list<string>> $links */
            public function __construct(private readonly CrawlerTest $test, private readonly array $links, private readonly ?string $failOn) {}

            public function audit(Url $url): PageVisit
            {
                $this->test->recordAudit($url);
                if ((string) $url === $this->failOn) {
                    throw new RuntimeException('browser crashed');
                }

                return new PageVisit(
                    PageResult::audited($url, null, 200, [], 10),
                    array_map($url->resolve(...), $this->links[(string) $url] ?? []),
                );
            }
        };

        return new Crawler($robotsPolicy, $throttle, $auditor, $options);
    }

    /** @internal called by the fake auditor */
    public function recordAudit(Url $url): void
    {
        $this->audited[] = (string) $url;
        $this->auditTimes[] = $this->clock->now()->format('H:i:s');
    }
}
