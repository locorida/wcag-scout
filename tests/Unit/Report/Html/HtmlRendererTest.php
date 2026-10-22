<?php

declare(strict_types=1);

namespace WcagScout\Tests\Unit\Report\Html;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use WcagScout\Report\Disclaimer;
use WcagScout\Report\Html\HtmlRenderer;
use WcagScout\Report\ReportOptions;
use WcagScout\Standards\En301549Version;
use WcagScout\Tests\Support\Path;
use WcagScout\Tests\Support\RunFactory;

final class HtmlRendererTest extends TestCase
{
    public function testGermanReportHasLanguageDisclaimerAndManualChecks(): void
    {
        $html = new HtmlRenderer()->html(RunFactory::sample(), new ReportOptions('de'));
        $xpath = self::xpath($html);

        self::assertSame('de', $xpath->evaluate('string(/html/@lang)'));
        self::assertStringContainsString(Disclaimer::for('de')['text'], $html);
        foreach (Disclaimer::for('de')['manualChecks'] as $check) {
            self::assertStringContainsString(htmlspecialchars($check, ENT_QUOTES), $html);
        }
        self::assertStringContainsString('1.4.3 Kontrast (Minimum)', $html);
        self::assertStringContainsString('EN 301 549: 9.1.4.3', $html);
        self::assertStringContainsString('Schließt manche Menschen vollständig aus.', $html);
    }

    public function testEnglishReport(): void
    {
        $html = new HtmlRenderer()->html(RunFactory::sample(), new ReportOptions('en'));

        self::assertStringContainsString('<html lang="en">', $html);
        self::assertStringContainsString(Disclaimer::for('en')['text'], $html);
        self::assertStringContainsString('1.4.3 Contrast (Minimum)', $html);
        self::assertStringContainsString('Excluded by the site&#039;s robots.txt.', $html);
        self::assertStringContainsString('Could not be loaded: HTTP 404', $html);
    }

    public function testEscapesEverythingTakenFromTheAuditedSite(): void
    {
        $html = new HtmlRenderer()->html(RunFactory::sample(), new ReportOptions());

        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringContainsString('Contact &lt;Shop&gt;', $html);
    }

    public function testDocumentStructure(): void
    {
        $xpath = self::xpath(new HtmlRenderer()->html(RunFactory::sample(), new ReportOptions()));

        self::assertSame(1.0, $xpath->evaluate('count(//h1)'));
        self::assertSame(1.0, $xpath->evaluate('count(//main)'));
        self::assertSame('#summary', $xpath->evaluate('string(//a[contains(@class, "skip-link")]/@href)'));
        self::assertSame(1.0, $xpath->evaluate('count(//*[@id="summary"])'), 'skip link target exists');
        self::assertSame(0.0, $xpath->evaluate('count(//img[not(@alt) or @alt=""])'), 'every screenshot has alt text');
        self::assertSame(0.0, $xpath->evaluate('count(//table[not(caption)])'), 'every table has a caption');

        // Heading levels never skip one.
        $previous = 1;
        foreach (Path::elements($xpath, '//h1|//h2|//h3|//h4|//h5|//h6') as $heading) {
            $level = (int) substr($heading->nodeName, 1);
            self::assertLessThanOrEqual($previous + 1, $level, 'heading level skipped at: ' . trim($heading->textContent));
            $previous = $level;
        }

        // IDs are unique.
        $ids = array_map(static fn(DOMElement $e): string => $e->getAttribute('id'), Path::elements($xpath, '//*[@id]'));
        self::assertSame(array_values(array_unique($ids)), $ids);
    }

    public function testPrintVersionExpandsAllDetails(): void
    {
        $renderer = new HtmlRenderer();
        $screen = self::xpath($renderer->html(RunFactory::sample(), new ReportOptions()));
        $print = self::xpath($renderer->html(RunFactory::sample(), new ReportOptions(), forPrint: true));

        self::assertGreaterThan(0.0, $screen->evaluate('count(//details[not(@open)])'));
        self::assertSame(0.0, $print->evaluate('count(//details[not(@open)])'));
    }

    public function testNamesTheSelectedEnVersion(): void
    {
        $html = new HtmlRenderer()->html(RunFactory::sample(), new ReportOptions(en301549: En301549Version::V4_1_1));

        self::assertStringContainsString('EN 301 549 V4.1.1', $html);
        self::assertStringNotContainsString('V3.2.1 is based on WCAG 2.1', $html);
    }

    private static function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument();
        self::assertTrue(@$document->loadHTML($html, LIBXML_NOERROR));

        return new DOMXPath($document);
    }
}
