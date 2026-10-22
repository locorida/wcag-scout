<?php

declare(strict_types=1);

namespace WcagScout\Tests\Unit\Model;

use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;
use WcagScout\Model\AuditRun;
use WcagScout\Model\Finding;
use WcagScout\Model\PageResult;
use WcagScout\Model\PageStatus;
use WcagScout\Model\Severity;
use WcagScout\Model\Url;
use WcagScout\Standards\Level;
use WcagScout\Standards\WcagCatalog;

final class AuditRunTest extends TestCase
{
    public function testAggregatesFindingsAcrossPages(): void
    {
        $home = Url::parse('http://localhost/');
        $about = Url::parse('http://localhost/about');
        $run = new AuditRun('1.0.0', new DateTimeImmutable('2026-01-01 10:00:00'), new DateTimeImmutable('2026-01-01 10:01:05'), ['axe-core' => '4.14.0'], [], [
            PageResult::audited($home, 'Home', 200, [
                self::finding('image-alt', Severity::Critical, $home, ['1.1.1']),
                self::finding('color-contrast', Severity::Serious, $home, ['1.4.3']),
            ], 900),
            PageResult::audited($about, 'About', 200, [
                self::finding('color-contrast', Severity::Serious, $about, ['1.4.3']),
                self::finding('region', Severity::Moderate, $about, []),
            ], 800),
            PageResult::skipped(Url::parse('http://localhost/private'), 'robots.txt'),
            PageResult::failed(Url::parse('http://localhost/broken'), 'HTTP 500', 500),
        ]);

        self::assertCount(4, $run->findings());
        self::assertSame(['critical' => 1, 'serious' => 2, 'moderate' => 1, 'minor' => 0], $run->countBySeverity());
        self::assertSame(['image-alt', 'color-contrast', 'region'], array_keys($run->findingsByRule()));
        self::assertCount(2, $run->pagesWithStatus(PageStatus::Audited));
        self::assertCount(1, $run->pagesWithStatus(PageStatus::Skipped));
        self::assertSame(65, $run->durationSeconds());
    }

    public function testRulesWithSameSeverityAreOrderedByNumberOfFindings(): void
    {
        $page = Url::parse('http://localhost/');
        $run = new AuditRun('1.0.0', new DateTimeImmutable(), new DateTimeImmutable(), [], [], [
            PageResult::audited($page, null, 200, [
                self::finding('label', Severity::Critical, $page, ['4.1.2'], '#a'),
                self::finding('image-alt', Severity::Critical, $page, ['1.1.1'], '#b'),
                self::finding('image-alt', Severity::Critical, $page, ['1.1.1'], '#c'),
            ], 100),
        ]);

        self::assertSame(['image-alt', 'label'], array_keys($run->findingsByRule()));
    }

    public function testFindingKnowsItsLevelAndBestPracticeStatus(): void
    {
        $page = Url::parse('http://localhost/');

        $multi = self::finding('link-name', Severity::Serious, $page, ['2.4.4', '4.1.2', '1.4.3']);
        self::assertSame(Level::A, $multi->level());
        self::assertFalse($multi->isBestPractice());

        $bestPractice = self::finding('region', Severity::Moderate, $page, []);
        self::assertNull($bestPractice->level());
        self::assertTrue($bestPractice->isBestPractice());
    }

    public function testRejectsFindingsOfAnotherPage(): void
    {
        $this->expectException(LogicException::class);
        PageResult::audited(Url::parse('http://localhost/a'), null, 200, [
            self::finding('image-alt', Severity::Critical, Url::parse('http://localhost/b'), ['1.1.1']),
        ], 0);
    }

    public function testFingerprintIgnoresHtmlAndSelectorWhitespace(): void
    {
        $page = Url::parse('http://localhost/');
        $a = new Finding('image-alt', 'axe-core', Severity::Critical, [], $page, 'main  > img', '<img src="a.png?v=1">', '', '');
        $b = new Finding('image-alt', 'axe-core', Severity::Critical, [], $page, ' main > img ', '<img src="a.png?v=2">', '', '');
        $c = new Finding('image-alt', 'axe-core', Severity::Critical, [], Url::parse('http://localhost/#x'), 'main > img', '', '', '');
        $d = new Finding('image-alt', 'axe-core', Severity::Critical, [], Url::parse('http://localhost/other'), 'main > img', '', '', '');

        self::assertSame((string) $a->fingerprint, (string) $b->fingerprint);
        self::assertSame((string) $a->fingerprint, (string) $c->fingerprint, 'fragment is not part of the page identity');
        self::assertNotSame((string) $a->fingerprint, (string) $d->fingerprint);
    }

    public function testSeverity(): void
    {
        self::assertSame(Severity::Moderate, Severity::fromAxeImpact(null));
        self::assertSame(Severity::Serious, Severity::fromOption('SERIOUS'));
        self::assertTrue(Severity::Critical->isAtLeast(Severity::Serious));
        self::assertFalse(Severity::Minor->isAtLeast(Severity::Moderate));
    }

    /** @param list<string> $criteria */
    private static function finding(string $rule, Severity $severity, Url $page, array $criteria, string $selector = 'body'): Finding
    {
        $catalog = new WcagCatalog();

        return new Finding(
            $rule,
            'axe-core',
            $severity,
            array_map($catalog->get(...), $criteria),
            $page,
            $selector,
            '<body>',
            'summary',
            'https://example.com/help',
        );
    }
}
