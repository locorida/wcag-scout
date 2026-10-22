<?php

declare(strict_types=1);

namespace WcagScout\Report\Json;

use DateTimeInterface;
use WcagScout\Model\AuditRun;
use WcagScout\Model\Finding;
use WcagScout\Model\PageResult;
use WcagScout\Model\RuleInfo;
use WcagScout\Report\Disclaimer;
use WcagScout\Standards\En301549Version;
use WcagScout\Standards\SuccessCriterion;

/**
 * Writes an AuditRun as JSON. This is the canonical result format: every other format can be
 * rendered from it again (render, baseline and compare read it back with RunDeserializer).
 *
 * Changes that break readers increase SCHEMA_VERSION.
 */
final readonly class RunSerializer
{
    public const int SCHEMA_VERSION = 1;

    public function serialize(AuditRun $run): string
    {
        return json_encode($this->normalize($run), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }

    /** @return array<string, mixed> */
    public function normalize(AuditRun $run): array
    {
        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'tool' => ['name' => 'wcag-scout', 'version' => $run->toolVersion],
            'engines' => $run->engines,
            'startedAt' => $run->startedAt->format(DateTimeInterface::ATOM),
            'finishedAt' => $run->finishedAt->format(DateTimeInterface::ATOM),
            'options' => $run->options,
            'disclaimer' => Disclaimer::all(),
            'summary' => [
                'pages' => \count($run->pages),
                'findings' => \count($run->findings()),
                'bySeverity' => $run->countBySeverity(),
            ],
            'rules' => array_map(self::rule(...), $run->rules),
            'pages' => array_map(self::page(...), $run->pages),
        ];
    }

    /** @return array<string, mixed> */
    private static function rule(RuleInfo $rule): array
    {
        return [
            'id' => $rule->id,
            'engine' => $rule->engine,
            'help' => $rule->help,
            'description' => $rule->description,
            'helpUrl' => $rule->helpUrl,
            'tags' => $rule->tags,
        ];
    }

    /** @return array<string, mixed> */
    private static function page(PageResult $page): array
    {
        return [
            'url' => (string) $page->url,
            'status' => $page->status->value,
            'reason' => $page->reason,
            'title' => $page->title,
            'httpStatus' => $page->httpStatus,
            'durationMs' => $page->durationMs,
            'findings' => array_map(self::finding(...), $page->findings),
        ];
    }

    /** @return array<string, mixed> */
    private static function finding(Finding $finding): array
    {
        return [
            'fingerprint' => (string) $finding->fingerprint,
            'ruleId' => $finding->ruleId,
            'engine' => $finding->engine,
            'severity' => $finding->severity->value,
            'bestPractice' => $finding->isBestPractice(),
            'criteria' => array_map(static fn(SuccessCriterion $c): array => [
                'number' => $c->number,
                'level' => $c->level->value,
                // Both versions, so consumers can pick the one they need.
                'en301549' => array_combine(
                    array_column(En301549Version::cases(), 'value'),
                    array_map($c->en301549Clause(...), En301549Version::cases()),
                ),
            ], $finding->criteria),
            'selector' => $finding->selector,
            'html' => $finding->html,
            'summary' => $finding->summary,
            'helpUrl' => $finding->helpUrl,
            'screenshot' => $finding->screenshot,
        ];
    }
}
