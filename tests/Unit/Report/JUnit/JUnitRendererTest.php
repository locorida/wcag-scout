<?php

declare(strict_types=1);

namespace WcagScout\Tests\Unit\Report\JUnit;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use WcagScout\Model\Severity;
use WcagScout\Report\JUnit\JUnitRenderer;
use WcagScout\Report\ReportOptions;
use WcagScout\Tests\Support\Path;
use WcagScout\Tests\Support\RunFactory;

final class JUnitRendererTest extends TestCase
{
    public function testOneSuitePerPageAndOneCasePerRule(): void
    {
        $xpath = self::xpath(new JUnitRenderer()->xml(RunFactory::sample(), new ReportOptions()));

        self::assertSame(4.0, $xpath->evaluate('count(/testsuites/testsuite)'));
        self::assertSame('https://shop.example/', $xpath->evaluate('string(/testsuites/testsuite[1]/@name)'));
        // Home: image-alt, link-name (failures), region (best practice: passes, listed in system-out).
        self::assertSame('3', $xpath->evaluate('string(/testsuites/testsuite[1]/@tests)'));
        self::assertSame('2', $xpath->evaluate('string(/testsuites/testsuite[1]/@failures)'));
        self::assertStringContainsString('(WCAG 1.1.1 (A))', Path::string($xpath, '/testsuites/testsuite[1]/testcase[1]/@name'));
        self::assertSame('2 × Images without a text alternative', $xpath->evaluate('string(//testcase[1]/failure/@message)'));
        self::assertSame(1.0, $xpath->evaluate('count(//testcase[starts-with(@name, "region")]/system-out)'));

        self::assertSame('4', $xpath->evaluate('string(/testsuites/@failures)'));
        self::assertSame('1', $xpath->evaluate('string(/testsuites/@errors)'), 'page with HTTP 404');
        self::assertSame('1', $xpath->evaluate('string(/testsuites/@skipped)'), 'page excluded by robots.txt');
    }

    public function testThresholdAndBaselineDecideWhatFails(): void
    {
        $run = RunFactory::sample();
        $known = [];
        foreach ($run->findings() as $finding) {
            if ($finding->ruleId === 'image-alt') {
                $known[(string) $finding->fingerprint] = true;
            }
        }

        $xpath = self::xpath(new JUnitRenderer()->xml($run, new ReportOptions(knownFingerprints: $known, failOn: Severity::Serious)));

        // Left over: link-name and color-contrast, both serious and new.
        self::assertSame('2', $xpath->evaluate('string(/testsuites/@failures)'));
        self::assertStringContainsString('[known]', Path::string($xpath, '//testcase[starts-with(@name, "image-alt")]/system-out'));
    }

    private static function xpath(string $xml): DOMXPath
    {
        $document = new DOMDocument();
        self::assertTrue($document->loadXML($xml));

        return new DOMXPath($document);
    }
}
