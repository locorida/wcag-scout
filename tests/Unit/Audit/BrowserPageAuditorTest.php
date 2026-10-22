<?php

declare(strict_types=1);

namespace WcagScout\Tests\Unit\Audit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WcagScout\Audit\BrowserPageAuditor;
use WcagScout\Audit\ScreenshotWriter;
use WcagScout\Browser\NavigationInfo;
use WcagScout\Model\Finding;
use WcagScout\Model\PageResult;
use WcagScout\Model\PageStatus;
use WcagScout\Model\Url;
use WcagScout\Tests\Support\FakeEngine;
use WcagScout\Tests\Support\FakeLoader;
use WcagScout\Tests\Support\FakePage;
use WcagScout\Tests\Support\TempDirectory;

final class BrowserPageAuditorTest extends TestCase
{
    private string $reportDir;

    protected function setUp(): void
    {
        $this->reportDir = TempDirectory::path('wcag-scout-test');
    }

    protected function tearDown(): void
    {
        TempDirectory::remove($this->reportDir);
    }

    public function testAuditsPageWithAllEnginesAndCollectsLinks(): void
    {
        $page = new FakePage(new NavigationInfo('http://localhost/a', 200, 'text/html', 'Page A'), ['http://localhost/b', 'mailto:x@example.com']);
        $auditor = new BrowserPageAuditor(new FakeLoader($page), [new FakeEngine('one', 2), new FakeEngine('two', 1)], collectLinks: true);

        $visit = $auditor->audit(Url::parse('http://localhost/a'));

        self::assertSame(PageStatus::Audited, $visit->result->status);
        self::assertSame('Page A', $visit->result->title);
        self::assertCount(3, $visit->result->findings);
        self::assertSame(['http://localhost/b'], array_map(strval(...), $visit->links));
        self::assertTrue($page->closed);
    }

    public function testTakesLimitedScreenshotsPerRule(): void
    {
        $page = new FakePage(new NavigationInfo('http://localhost/', 200, 'text/html', null));
        $auditor = new BrowserPageAuditor(new FakeLoader($page), [new FakeEngine('rule', 4)], new ScreenshotWriter($this->reportDir, 3));

        $findings = $auditor->audit(Url::parse('http://localhost/'))->result->findings;

        $paths = array_map(static fn(Finding $f): ?string => $f->screenshot, $findings);
        self::assertCount(3, array_filter($paths, \is_string(...)));
        self::assertNull($paths[3]);
        self::assertFileExists($this->reportDir . '/' . $paths[0]);
    }

    /** @return iterable<string, array{NavigationInfo, PageStatus, string|null}> */
    public static function pagesNotAudited(): iterable
    {
        yield 'HTTP error' => [new NavigationInfo('http://localhost/', 404, 'text/html', null), PageStatus::Failed, 'HTTP 404'];
        yield 'redirect to another site' => [new NavigationInfo('https://elsewhere.example/', 200, 'text/html', null), PageStatus::Skipped, PageResult::SKIPPED_OUT_OF_SCOPE];
        yield 'PDF' => [new NavigationInfo('http://localhost/', 200, 'application/pdf', null), PageStatus::Skipped, PageResult::SKIPPED_NOT_HTML];
    }

    #[DataProvider('pagesNotAudited')]
    public function testDoesNotAuditUnsuitablePages(NavigationInfo $info, PageStatus $status, ?string $reason): void
    {
        $engine = new FakeEngine('rule', 1);
        $page = new FakePage($info);

        $result = new BrowserPageAuditor(new FakeLoader($page), [$engine])->audit(Url::parse('http://localhost/'))->result;

        self::assertSame($status, $result->status);
        self::assertSame($reason, $result->reason);
        self::assertSame(0, $engine->runs);
        self::assertTrue($page->closed);
    }

    public function testBrowserErrorsBecomeFailedPages(): void
    {
        $result = new BrowserPageAuditor(new FakeLoader(null), [])->audit(Url::parse('http://localhost/'))->result;

        self::assertSame(PageStatus::Failed, $result->status);
        self::assertSame('net::ERR_CONNECTION_REFUSED', $result->reason);
    }
}
