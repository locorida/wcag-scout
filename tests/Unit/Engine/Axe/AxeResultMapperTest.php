<?php

declare(strict_types=1);

namespace WcagScout\Tests\Unit\Engine\Axe;

use PHPUnit\Framework\TestCase;
use WcagScout\Engine\Axe\AxeResultMapper;
use WcagScout\Model\Finding;
use WcagScout\Model\Severity;
use WcagScout\Model\Url;
use WcagScout\Standards\SuccessCriterion;

final class AxeResultMapperTest extends TestCase
{
    public function testMapsEveryNodeToAFinding(): void
    {
        $url = Url::parse('http://localhost/page');
        $result = new AxeResultMapper()->map(self::violations(), $url);

        self::assertCount(3, $result->findings);
        [$first, $second, $third] = $result->findings;

        self::assertSame('image-alt', $first->ruleId);
        self::assertSame('axe-core', $first->engine);
        self::assertSame(Severity::Critical, $first->severity);
        self::assertSame(['1.1.1'], self::numbers($first));
        self::assertSame('img[width="120"]', $first->selector);
        self::assertSame('<img src="/logo.svg" width="120">', $first->html);
        self::assertStringStartsWith('Fix any of the following:', $first->summary);
        self::assertSame('https://dequeuniversity.com/rules/axe/4.14/image-alt?application=axeAPI', $first->helpUrl);
        self::assertTrue($first->pageUrl->equals($url));

        self::assertSame('#second-image', $second->selector);

        self::assertSame('region', $third->ruleId);
        self::assertTrue($third->isBestPractice());
        self::assertSame(Severity::Moderate, $third->severity, 'rule impact is used when the node has none');
        self::assertSame('#app >>> custom-widget >>> button', $third->selector, 'frames and shadow DOM are flattened');

        self::assertSame(['image-alt', 'region'], array_keys($result->rules));
        self::assertSame('Images must have alternative text', $result->rules['image-alt']->help);
    }

    public function testIgnoresMalformedEntries(): void
    {
        $result = new AxeResultMapper()->map([null, 'x', ['id' => 'no-nodes'], ['nodes' => [[]]]], Url::parse('http://localhost/'));

        self::assertSame([], $result->findings);
        self::assertSame(['no-nodes'], array_keys($result->rules));
    }

    /** @return list<string> */
    private static function numbers(Finding $finding): array
    {
        return array_map(static fn(SuccessCriterion $c): string => $c->number, $finding->criteria);
    }

    /** @return list<array<string, mixed>> shape of axe-core 4.14 results, trimmed */
    private static function violations(): array
    {
        return [
            [
                'id' => 'image-alt',
                'impact' => 'critical',
                'tags' => ['cat.text-alternatives', 'wcag2a', 'wcag111', 'section508', 'EN-301-549', 'EN-9.1.1.1', 'ACT'],
                'description' => 'Ensure <img> elements have alternative text or a role of none or presentation',
                'help' => 'Images must have alternative text',
                'helpUrl' => 'https://dequeuniversity.com/rules/axe/4.14/image-alt?application=axeAPI',
                'nodes' => [
                    [
                        'impact' => 'critical',
                        'html' => '<img src="/logo.svg" width="120">',
                        'target' => ['img[width="120"]'],
                        'failureSummary' => "Fix any of the following:\n  Element does not have an alt attribute",
                    ],
                    [
                        'impact' => 'critical',
                        'html' => '<img src="/logo.svg" id="second-image">',
                        'target' => ['#second-image'],
                        'failureSummary' => "Fix any of the following:\n  Element does not have an alt attribute",
                    ],
                ],
            ],
            [
                'id' => 'region',
                'impact' => 'moderate',
                'tags' => ['cat.keyboard', 'best-practice'],
                'description' => 'Ensure all page content is contained by landmarks',
                'help' => 'All page content should be contained by landmarks',
                'helpUrl' => 'https://dequeuniversity.com/rules/axe/4.14/region?application=axeAPI',
                'nodes' => [
                    [
                        'impact' => null,
                        'html' => '<button>Go</button>',
                        'target' => ['#app', ['custom-widget', 'button']],
                        'failureSummary' => "Fix any of the following:\n  Some page content is not contained by landmarks",
                    ],
                ],
            ],
        ];
    }
}
