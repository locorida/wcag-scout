<?php

declare(strict_types=1);

namespace WcagScout\Tests\Unit\Report;

use PHPUnit\Framework\TestCase;
use WcagScout\Model\Severity;
use WcagScout\Model\Url;
use WcagScout\Report\ReportModel;
use WcagScout\Report\ReportOptions;
use WcagScout\Report\RuleGroup;
use WcagScout\Tests\Support\RunFactory;

final class ReportModelTest extends TestCase
{
    public function testGroupsByFirstCriterionInNumericOrder(): void
    {
        $model = new ReportModel(RunFactory::sample(), new ReportOptions());

        self::assertSame(['1.1.1', '1.4.3', '2.4.4'], array_map(static fn(array $c): string => $c['criterion']->number, $model->criteria));
        self::assertSame(3, $model->criteria[0]['count']);
        // link-name maps to 2.4.4 and 4.1.2: listed once, under 2.4.4, naming 4.1.2.
        self::assertSame('4.1.2', $model->criteria[2]['rules'][0]->otherCriteria()[0]->number);
        self::assertSame(['region'], array_map(static fn(RuleGroup $g): string => $g->id, $model->bestPractices));
    }

    public function testFiguresAndOwnTitles(): void
    {
        $model = new ReportModel(RunFactory::sample(), new ReportOptions('de'));

        self::assertSame(2, $model->auditedPageCount());
        self::assertSame(6, $model->findingCount);
        self::assertSame(4, $model->ruleCount());
        self::assertSame(4, $model->criteriaCount(), '1.1.1, 1.4.3, 2.4.4 and 4.1.2');
        self::assertCount(2, $model->notAudited);
        self::assertSame('Bilder ohne Textalternative', $model->topIssues[0]['title']);
        self::assertSame(3, $model->topIssues[0]['count']);
        self::assertSame(2, $model->topIssues[0]['pages']);
        self::assertSame('https://shop.example/', (string) $model->pages[0]['page']->url, 'page with most findings first');
    }

    public function testFallsBackToEngineTextForRulesWithoutExplanation(): void
    {
        $model = new ReportModel(RunFactory::sample(), new ReportOptions());

        self::assertNull($model->explanation('some-unknown-rule'));
        self::assertSame('some-unknown-rule', $model->title('some-unknown-rule'));
    }

    public function testMarksFindingsMissingFromTheBaselineAsNew(): void
    {
        $run = RunFactory::sample();
        $known = [(string) $run->findings()[0]->fingerprint => true];
        $model = new ReportModel($run, new ReportOptions(knownFingerprints: $known));

        self::assertFalse($model->isNew($run->findings()[0]));
        self::assertTrue($model->isNew($run->findings()[1]));
        self::assertSame(5, $model->newCount);

        $withoutBaseline = new ReportModel($run, new ReportOptions());
        self::assertFalse($withoutBaseline->isNew($run->findings()[1]), 'without baseline nothing is "new"');
    }

    public function testFailingRule(): void
    {
        $page = Url::parse('https://shop.example/');
        $critical = RunFactory::finding('image-alt', Severity::Critical, $page, ['1.1.1'], 'img');
        $minor = RunFactory::finding('image-alt', Severity::Minor, $page, ['1.1.1'], 'img.small');
        $bestPractice = RunFactory::finding('region', Severity::Critical, $page, [], 'div');

        $default = new ReportOptions();
        self::assertTrue($default->isFailing($critical));
        self::assertTrue($default->isFailing($minor));
        self::assertFalse($default->isFailing($bestPractice), 'best practices never fail a run');

        $serious = new ReportOptions(failOn: Severity::Serious);
        self::assertTrue($serious->isFailing($critical));
        self::assertFalse($serious->isFailing($minor));

        self::assertFalse(new ReportOptions(failOn: null)->isFailing($critical));

        $baseline = new ReportOptions(knownFingerprints: [(string) $critical->fingerprint => true]);
        self::assertFalse($baseline->isFailing($critical), 'known findings do not fail');
        self::assertTrue($baseline->isFailing($minor));
    }
}
