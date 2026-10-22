<?php

declare(strict_types=1);

namespace WcagScout\Report;

use WcagScout\Knowledge\KnowledgeBase;
use WcagScout\Knowledge\RuleExplanation;
use WcagScout\Model\AuditRun;
use WcagScout\Model\Finding;
use WcagScout\Model\PageResult;
use WcagScout\Model\PageStatus;
use WcagScout\Model\Severity;
use WcagScout\Standards\SuccessCriterion;

/**
 * Everything a human-readable report shows, prepared once so templates stay free of logic.
 *
 * Findings are grouped by WCAG criterion, then by rule. A rule that maps to several
 * criteria (link-name: 2.4.4 and 4.1.2) is listed once, under its first criterion, and
 * names the others; listing it twice would double the counts.
 */
final readonly class ReportModel
{
    /** @var list<array{criterion: SuccessCriterion, rules: list<RuleGroup>, count: int}> */
    public array $criteria;

    /** @var list<RuleGroup> */
    public array $bestPractices;

    /** @var list<array{rule: string, title: string, severity: Severity, count: int, pages: int}> */
    public array $topIssues;

    /** @var list<array{page: PageResult, counts: array<string, int>, total: int}> */
    public array $pages;

    /** @var list<PageResult> */
    public array $notAudited;

    public int $findingCount;
    public int $newCount;

    public function __construct(
        public AuditRun $run,
        public ReportOptions $options,
        private KnowledgeBase $knowledge = new KnowledgeBase(),
    ) {
        $byCriterion = [];
        $bestPractices = [];
        foreach ($run->findingsByRule() as $ruleId => $findings) {
            $group = $this->ruleGroup($ruleId, $findings);
            $criterion = $findings[0]->criteria[0] ?? null;
            if ($criterion === null) {
                $bestPractices[] = $group;
            } else {
                $byCriterion[$criterion->number]['criterion'] = $criterion;
                $byCriterion[$criterion->number]['rules'][] = $group;
            }
        }
        uksort($byCriterion, static fn(string $a, string $b): int => version_compare($a, $b));

        $this->criteria = array_values(array_map(static fn(array $c): array => [
            'criterion' => $c['criterion'],
            'rules' => $c['rules'],
            'count' => array_sum(array_map(static fn(RuleGroup $g): int => $g->count, $c['rules'])),
        ], $byCriterion));
        $this->bestPractices = $bestPractices;

        $top = [];
        foreach ($run->findingsByRule() as $ruleId => $findings) {
            $top[] = [
                'rule' => $ruleId,
                'title' => $this->title($ruleId),
                'severity' => $findings[0]->severity,
                'count' => \count($findings),
                'pages' => \count(array_unique(array_map(static fn(Finding $f): string => (string) $f->pageUrl, $findings))),
            ];
        }
        $this->topIssues = \array_slice($top, 0, 10);

        $pages = [];
        foreach ($run->pagesWithStatus(PageStatus::Audited) as $page) {
            $counts = array_fill_keys(array_map(static fn(Severity $s): string => $s->value, Severity::ordered()), 0);
            foreach ($page->findings as $finding) {
                ++$counts[$finding->severity->value];
            }
            $pages[] = ['page' => $page, 'counts' => $counts, 'total' => \count($page->findings)];
        }
        usort($pages, static fn(array $a, array $b): int => $b['total'] <=> $a['total']);
        $this->pages = $pages;

        $this->notAudited = array_values(array_filter($run->pages, static fn(PageResult $p): bool => $p->status !== PageStatus::Audited));

        $findings = $run->findings();
        $this->findingCount = \count($findings);
        $this->newCount = \count(array_filter($findings, $this->isNew(...)));
    }

    public function isNew(Finding $finding): bool
    {
        return $this->options->hasBaseline() && !$this->options->isKnown($finding->fingerprint);
    }

    public function explanation(string $ruleId): ?RuleExplanation
    {
        return $this->knowledge->explain($ruleId, $this->options->language);
    }

    /** Own title if there is one, otherwise the engine's help text. */
    public function title(string $ruleId): string
    {
        return $this->explanation($ruleId)->title ?? $this->run->rule($ruleId)->help ?? $ruleId;
    }

    public function ruleCount(): int
    {
        return \count($this->run->findingsByRule());
    }

    public function auditedPageCount(): int
    {
        return \count($this->run->pagesWithStatus(PageStatus::Audited));
    }

    public function criteriaCount(): int
    {
        $numbers = [];
        foreach ($this->run->findings() as $finding) {
            foreach ($finding->criteria as $criterion) {
                $numbers[$criterion->number] = true;
            }
        }

        return \count($numbers);
    }

    /** @param non-empty-list<Finding> $findings all findings of one rule */
    private function ruleGroup(string $ruleId, array $findings): RuleGroup
    {
        $byPage = [];
        foreach ($findings as $finding) {
            $byPage[(string) $finding->pageUrl][] = $finding;
        }
        $first = $findings[0];

        return new RuleGroup(
            $ruleId,
            $this->title($ruleId),
            $this->explanation($ruleId),
            $this->run->rule($ruleId)->description ?? '',
            $first->helpUrl,
            $first->severity,
            $first->criteria,
            \count($findings),
            $byPage,
        );
    }
}
