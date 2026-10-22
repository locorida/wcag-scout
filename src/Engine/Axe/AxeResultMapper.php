<?php

declare(strict_types=1);

namespace WcagScout\Engine\Axe;

use WcagScout\Model\Finding;
use WcagScout\Model\RuleInfo;
use WcagScout\Model\Severity;
use WcagScout\Model\Url;
use WcagScout\Standards\WcagCatalog;

/**
 * Turns axe-core's "violations" array into findings: one finding per affected element.
 */
final readonly class AxeResultMapper
{
    /** Separates frame and shadow-DOM levels in a selector path. */
    public const string SELECTOR_SEPARATOR = ' >>> ';

    public function __construct(
        private WcagCatalog $catalog = new WcagCatalog(),
    ) {}

    /** @param array<mixed> $violations decoded axe-core result */
    public function map(array $violations, Url $url): MappedResult
    {
        $findings = [];
        $rules = [];

        foreach ($violations as $violation) {
            if (!\is_array($violation) || !\is_string($violation['id'] ?? null)) {
                continue;
            }
            $ruleId = $violation['id'];
            $tags = self::strings($violation['tags'] ?? []);
            $criteria = $this->catalog->fromAxeTags($tags);
            $helpUrl = self::string($violation['helpUrl'] ?? '');
            $ruleImpact = self::string($violation['impact'] ?? '');

            $rules[$ruleId] = new RuleInfo(
                $ruleId,
                AxeEngine::NAME,
                self::string($violation['help'] ?? $ruleId),
                self::string($violation['description'] ?? ''),
                $helpUrl,
                $tags,
            );

            foreach (\is_array($violation['nodes'] ?? null) ? $violation['nodes'] : [] as $node) {
                if (!\is_array($node)) {
                    continue;
                }
                $nodeImpact = self::string($node['impact'] ?? '');
                $impact = $nodeImpact !== '' ? $nodeImpact : $ruleImpact;
                $findings[] = new Finding(
                    $ruleId,
                    AxeEngine::NAME,
                    Severity::tryFrom($impact) ?? Severity::Moderate,
                    $criteria,
                    $url,
                    self::selector($node['target'] ?? []),
                    self::string($node['html'] ?? ''),
                    trim(self::string($node['failureSummary'] ?? '')),
                    $helpUrl,
                );
            }
        }

        return new MappedResult($findings, $rules);
    }

    /**
     * axe-core targets are a list of selectors, one per frame; inside shadow DOM an entry is
     * itself a list (host, then the element inside it).
     */
    private static function selector(mixed $target): string
    {
        if (!\is_array($target)) {
            return '';
        }
        $parts = [];
        foreach ($target as $entry) {
            if (\is_array($entry)) {
                $parts = [...$parts, ...self::strings($entry)];
            } elseif (\is_string($entry)) {
                $parts[] = $entry;
            }
        }

        return implode(self::SELECTOR_SEPARATOR, $parts);
    }

    /** @return list<string> */
    private static function strings(mixed $value): array
    {
        return \is_array($value) ? array_values(array_filter($value, \is_string(...))) : [];
    }

    private static function string(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }
}
