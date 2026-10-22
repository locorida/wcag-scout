<?php

declare(strict_types=1);

namespace WcagScout\Tests\Support;

use DateTimeImmutable;
use WcagScout\Model\AuditRun;
use WcagScout\Model\Finding;
use WcagScout\Model\PageResult;
use WcagScout\Model\RuleInfo;
use WcagScout\Model\Severity;
use WcagScout\Model\Url;
use WcagScout\Standards\WcagCatalog;

/**
 * A small but complete audit run for report tests.
 */
final class RunFactory
{
    public static function sample(): AuditRun
    {
        $home = Url::parse('https://shop.example/');
        $contact = Url::parse('https://shop.example/contact');

        return new AuditRun(
            '1.2.3',
            new DateTimeImmutable('2026-03-01T09:30:00+00:00'),
            new DateTimeImmutable('2026-03-01T09:31:12+00:00'),
            ['axe-core' => '4.14.0'],
            ['target' => 'https://shop.example/', 'language' => 'en', 'followLinks' => false, 'maxPages' => 50, 'delaySeconds' => 1.0],
            [
                PageResult::audited($home, 'Shop – Home', 200, [
                    self::finding('image-alt', Severity::Critical, $home, ['1.1.1'], 'main > img:nth-child(1)', '<img src="hero.jpg">', 'screenshots/a.png'),
                    self::finding('image-alt', Severity::Critical, $home, ['1.1.1'], 'main > img:nth-child(2)', '<img src="sale.jpg">'),
                    self::finding('link-name', Severity::Serious, $home, ['2.4.4', '4.1.2'], 'header > a', '<a href="/"><svg></svg></a>'),
                    self::finding('region', Severity::Moderate, $home, [], 'body > div.banner', '<div class="banner">Sale</div>'),
                ], 1250),
                PageResult::audited($contact, 'Contact <Shop>', 200, [
                    self::finding('color-contrast', Severity::Serious, $contact, ['1.4.3'], 'p.hint', '<p class="hint"><script>alert(1)</script>Grey</p>'),
                    self::finding('image-alt', Severity::Critical, $contact, ['1.1.1'], 'img.map', '<img class="map">'),
                ], 980),
                PageResult::skipped(Url::parse('https://shop.example/admin/'), PageResult::SKIPPED_ROBOTS_TXT),
                PageResult::failed(Url::parse('https://shop.example/old'), 'HTTP 404', 404, 120),
            ],
            [
                'image-alt' => new RuleInfo('image-alt', 'axe-core', 'Images must have alternative text', 'Ensure <img> elements have alternative text', 'https://dequeuniversity.com/rules/axe/4.14/image-alt', ['wcag2a', 'wcag111']),
                'link-name' => new RuleInfo('link-name', 'axe-core', 'Links must have discernible text', 'Ensure links have discernible text', 'https://dequeuniversity.com/rules/axe/4.14/link-name', ['wcag2a', 'wcag244', 'wcag412']),
                'region' => new RuleInfo('region', 'axe-core', 'All page content should be contained by landmarks', 'Ensure all page content is contained by landmarks', 'https://dequeuniversity.com/rules/axe/4.14/region', ['best-practice']),
                'color-contrast' => new RuleInfo('color-contrast', 'axe-core', 'Elements must meet minimum color contrast ratio thresholds', 'Ensure the contrast is sufficient', 'https://dequeuniversity.com/rules/axe/4.14/color-contrast', ['wcag2aa', 'wcag143']),
            ],
        );
    }

    /** @param list<string> $criteria */
    public static function finding(
        string $rule,
        Severity $severity,
        Url $page,
        array $criteria,
        string $selector,
        string $html = '<p>',
        ?string $screenshot = null,
    ): Finding {
        $catalog = new WcagCatalog();

        return new Finding(
            $rule,
            'axe-core',
            $severity,
            array_map($catalog->get(...), $criteria),
            $page,
            $selector,
            $html,
            "Fix any of the following:\n  Something is missing",
            'https://dequeuniversity.com/rules/axe/4.14/' . $rule,
            $screenshot,
        );
    }
}
