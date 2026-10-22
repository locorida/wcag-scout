<?php

declare(strict_types=1);

namespace WcagScout\Knowledge;

use RuntimeException;

/**
 * Plain-language explanations for engine rules, from resources/knowledge/<language>.php.
 */
final class KnowledgeBase
{
    private const string DIRECTORY = __DIR__ . '/../../resources/knowledge';

    /** @var array<string, array<string, RuleExplanation>> language => rule id => explanation */
    private array $loaded = [];

    public function explain(string $ruleId, string $language): ?RuleExplanation
    {
        return $this->load($language)[$ruleId] ?? null;
    }

    /** @return list<string> rule ids with an explanation in $language */
    public function ruleIds(string $language): array
    {
        return array_keys($this->load($language));
    }

    /** @return array<string, RuleExplanation> */
    private function load(string $language): array
    {
        if (isset($this->loaded[$language])) {
            return $this->loaded[$language];
        }

        $file = self::DIRECTORY . '/' . basename($language) . '.php';
        if (!is_file($file)) {
            throw new RuntimeException(\sprintf('No explanations for language "%s".', $language));
        }
        /** @var array<string, array{title: string, why: string, who: string, fix: string, example?: string}> $entries */
        $entries = require $file;

        $explanations = [];
        foreach ($entries as $ruleId => $entry) {
            $explanations[$ruleId] = new RuleExplanation($entry['title'], $entry['why'], $entry['who'], $entry['fix'], $entry['example'] ?? null);
        }

        return $this->loaded[$language] = $explanations;
    }
}
