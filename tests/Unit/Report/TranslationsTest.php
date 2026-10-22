<?php

declare(strict_types=1);

namespace WcagScout\Tests\Unit\Report;

use PHPUnit\Framework\TestCase;
use WcagScout\Knowledge\KnowledgeBase;
use WcagScout\Report\Disclaimer;
use WcagScout\Report\TranslatorFactory;
use WcagScout\Standards\WcagCatalog;

/**
 * German and English must stay complete and in sync.
 */
final class TranslationsTest extends TestCase
{
    private const string ROOT = __DIR__ . '/../../../resources';

    public function testMessageFilesHaveTheSameKeys(): void
    {
        $en = require self::ROOT . '/translations/messages.en.php';
        $de = require self::ROOT . '/translations/messages.de.php';
        self::assertIsArray($en);
        self::assertIsArray($de);

        self::assertSame([], array_diff_key($en, $de), 'missing in German');
        self::assertSame([], array_diff_key($de, $en), 'missing in English');
    }

    public function testEveryCriterionHasATitleInBothLanguages(): void
    {
        foreach (['en', 'de'] as $language) {
            $translator = TranslatorFactory::create($language);
            foreach (new WcagCatalog()->all() as $criterion) {
                $title = $translator->trans($criterion->translationKey());
                self::assertNotSame($criterion->translationKey(), $title, "{$language}: no title for {$criterion->number}");
            }
        }
    }

    public function testKnowledgeBaseIsCompleteInBothLanguages(): void
    {
        $knowledge = new KnowledgeBase();
        $en = $knowledge->ruleIds('en');

        self::assertSame($en, $knowledge->ruleIds('de'));
        self::assertGreaterThanOrEqual(30, \count($en));
        foreach (['en', 'de'] as $language) {
            foreach ($en as $ruleId) {
                $explanation = $knowledge->explain($ruleId, $language);
                self::assertNotNull($explanation);
                foreach (['title', 'why', 'who', 'fix'] as $field) {
                    self::assertNotSame('', trim($explanation->{$field}), "{$language}/{$ruleId}.{$field} is empty");
                }
            }
        }
    }

    public function testKnowledgeBaseOnlyExplainsRulesThatExistInAxeCore(): void
    {
        $axe = (string) file_get_contents(self::ROOT . '/axe/axe.min.js');
        foreach (new KnowledgeBase()->ruleIds('en') as $ruleId) {
            self::assertStringContainsString('id:"' . $ruleId . '"', $axe, "unknown axe-core rule {$ruleId}");
        }
    }

    public function testDisclaimerExistsInBothLanguages(): void
    {
        self::assertNotSame(Disclaimer::for('en')['text'], Disclaimer::for('de')['text']);
        self::assertCount(\count(Disclaimer::for('en')['manualChecks']), Disclaimer::for('de')['manualChecks']);
        self::assertSame(Disclaimer::for('en'), Disclaimer::for('fr'), 'unknown languages fall back to English');
    }
}
