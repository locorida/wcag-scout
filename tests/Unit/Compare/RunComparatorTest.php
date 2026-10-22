<?php

declare(strict_types=1);

namespace WcagScout\Tests\Unit\Compare;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WcagScout\Compare\ComparisonRenderer;
use WcagScout\Compare\RunComparator;
use WcagScout\Model\AuditRun;
use WcagScout\Model\Finding;
use WcagScout\Model\PageResult;
use WcagScout\Model\Severity;
use WcagScout\Model\Url;
use WcagScout\Tests\Support\Path;
use WcagScout\Tests\Support\RunFactory;

final class RunComparatorTest extends TestCase
{
    public function testClassifiesFindings(): void
    {
        $home = Url::parse('https://shop.example/');
        $about = Url::parse('https://shop.example/about');
        $contact = Url::parse('https://shop.example/contact');

        $before = self::audit([
            PageResult::audited($home, null, 200, [
                RunFactory::finding('image-alt', Severity::Critical, $home, ['1.1.1'], 'img.a'),
                RunFactory::finding('image-alt', Severity::Critical, $home, ['1.1.1'], 'img.b'),
            ], 1),
            PageResult::audited($about, null, 200, [RunFactory::finding('label', Severity::Critical, $about, ['4.1.2'], 'input')], 1),
            PageResult::audited($contact, null, 200, [RunFactory::finding('color-contrast', Severity::Serious, $contact, ['1.4.3'], 'p')], 1),
        ]);
        $after = self::audit([
            PageResult::audited($home, null, 200, [
                RunFactory::finding('image-alt', Severity::Critical, $home, ['1.1.1'], 'img.a'),
                RunFactory::finding('link-name', Severity::Serious, $home, ['2.4.4'], 'a.icon'),
            ], 1),
            PageResult::audited($about, null, 200, [], 1),
            // The contact page timed out this time: its finding is not "fixed".
            PageResult::failed($contact, 'timeout'),
        ]);

        $comparison = new RunComparator()->compare($before, $after);

        self::assertSame(['img.a'], self::selectors($comparison->unchanged));
        self::assertSame(['img.b', 'input'], self::selectors($comparison->fixed));
        self::assertSame(['a.icon'], self::selectors($comparison->added));
        self::assertSame(['p'], self::selectors($comparison->notRechecked));
    }

    public function testRunComparedWithItselfIsUnchanged(): void
    {
        $run = RunFactory::sample();
        $comparison = new RunComparator()->compare($run, $run);

        self::assertCount(6, $comparison->unchanged);
        self::assertSame([], [...$comparison->added, ...$comparison->fixed, ...$comparison->notRechecked]);
    }

    public function testRendersAccessibleHtmlAndJson(): void
    {
        $empty = self::audit([PageResult::audited(Url::parse('https://shop.example/'), null, 200, [], 1)]);
        $comparison = new RunComparator()->compare($empty, RunFactory::sample());
        $renderer = new ComparisonRenderer();

        $html = $renderer->html($comparison, 'de');
        self::assertStringContainsString('<html lang="de">', $html);
        self::assertStringContainsString('Vergleich zweier Prüfungen', $html);
        self::assertStringContainsString('<code>main &gt; img:nth-child(1)</code>', $html, 'selectors are escaped');

        $json = json_decode($renderer->json($comparison), true);
        self::assertSame(['added' => 6, 'fixed' => 0, 'unchanged' => 0, 'notRechecked' => 0], Path::json($json, 'summary'));
        self::assertSame('image-alt', Path::json($json, 'added.0.ruleId'));
    }

    /** @param list<PageResult> $pages */
    private static function audit(array $pages): AuditRun
    {
        return new AuditRun('1.0.0', new DateTimeImmutable(), new DateTimeImmutable(), [], [], $pages);
    }

    /**
     * @param list<Finding> $findings
     * @return list<string>
     */
    private static function selectors(array $findings): array
    {
        $selectors = array_map(static fn(Finding $f): string => $f->selector, $findings);
        sort($selectors);

        return $selectors;
    }
}
