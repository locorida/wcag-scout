<?php

declare(strict_types=1);

namespace WcagScout\Report\JUnit;

use DateTimeInterface;
use RuntimeException;
use Symfony\Contracts\Translation\TranslatorInterface;
use WcagScout\Knowledge\KnowledgeBase;
use WcagScout\Model\AuditRun;
use WcagScout\Model\Finding;
use WcagScout\Model\PageResult;
use WcagScout\Model\PageStatus;
use WcagScout\Report\FileWriter;
use WcagScout\Report\Renderer;
use WcagScout\Report\ReportOptions;
use WcagScout\Report\TranslatorFactory;
use WcagScout\Standards\SuccessCriterion;
use XMLWriter;

/**
 * JUnit XML for CI systems: one test suite per page, one test case per rule with findings.
 *
 * A test case fails only for findings that fail the build (see ReportOptions::isFailing):
 * known findings from the baseline, best practices and findings below the threshold are
 * listed in <system-out> but do not fail.
 */
final readonly class JUnitRenderer implements Renderer
{
    public const string FILE = 'junit.xml';

    public function __construct(
        private KnowledgeBase $knowledge = new KnowledgeBase(),
    ) {}

    public function format(): string
    {
        return 'junit';
    }

    public function render(AuditRun $run, ReportOptions $options, string $directory): string
    {
        return FileWriter::write($directory . '/' . self::FILE, $this->xml($run, $options));
    }

    public function xml(AuditRun $run, ReportOptions $options): string
    {
        $translator = TranslatorFactory::create($options->language);
        $suites = array_map(fn(PageResult $page): array => $this->suite($page, $run, $options, $translator), $run->pages);

        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('testsuites');
        $xml->writeAttribute('name', 'wcag-scout');
        $xml->writeAttribute('tests', (string) array_sum(array_column($suites, 'tests')));
        $xml->writeAttribute('failures', (string) array_sum(array_column($suites, 'failures')));
        $xml->writeAttribute('errors', (string) array_sum(array_column($suites, 'errors')));
        $xml->writeAttribute('skipped', (string) array_sum(array_column($suites, 'skipped')));
        $xml->writeAttribute('time', (string) $run->durationSeconds());
        $xml->writeAttribute('timestamp', $run->startedAt->format(DateTimeInterface::ATOM));

        foreach ($suites as $suite) {
            $xml->startElement('testsuite');
            $xml->writeAttribute('name', $suite['name']);
            foreach (['tests', 'failures', 'errors', 'skipped'] as $counter) {
                $xml->writeAttribute($counter, (string) $suite[$counter]);
            }
            $xml->writeAttribute('time', \sprintf('%.3F', $suite['time']));
            foreach ($suite['cases'] as $case) {
                $this->writeCase($xml, $case);
            }
            $xml->endElement();
        }

        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /**
     * @return array{name: string, tests: int, failures: int, errors: int, skipped: int, time: float, cases: list<array{name: string, classname: string, result: string, message: string, body: string}>}
     */
    private function suite(PageResult $page, AuditRun $run, ReportOptions $options, TranslatorInterface $translator): array
    {
        $url = (string) $page->url;
        $cases = [];

        if ($page->status === PageStatus::Skipped) {
            $cases[] = ['name' => 'audit', 'classname' => $url, 'result' => 'skipped', 'message' => $translator->trans('reason.' . $page->reason), 'body' => ''];
        } elseif ($page->status === PageStatus::Failed) {
            $message = $translator->trans('reason.failed', ['%reason%' => (string) $page->reason]);
            $cases[] = ['name' => 'audit', 'classname' => $url, 'result' => 'error', 'message' => $message, 'body' => ''];
        } elseif ($page->findings === []) {
            $cases[] = ['name' => 'audit', 'classname' => $url, 'result' => 'passed', 'message' => '', 'body' => ''];
        } else {
            $byRule = [];
            foreach ($page->findings as $finding) {
                $byRule[$finding->ruleId][] = $finding;
            }
            foreach ($byRule as $ruleId => $findings) {
                $cases[] = $this->ruleCase($ruleId, $findings, $url, $run, $options, $translator);
            }
        }

        $count = static fn(string $result): int => \count(array_filter($cases, static fn(array $c): bool => $c['result'] === $result));

        return [
            'name' => $url,
            'tests' => \count($cases),
            'failures' => $count('failure'),
            'errors' => $count('error'),
            'skipped' => $count('skipped'),
            'time' => $page->durationMs / 1000,
            'cases' => $cases,
        ];
    }

    /**
     * @param list<Finding> $findings
     * @return array{name: string, classname: string, result: string, message: string, body: string}
     */
    private function ruleCase(string $ruleId, array $findings, string $url, AuditRun $run, ReportOptions $options, TranslatorInterface $translator): array
    {
        $title = $this->knowledge->explain($ruleId, $options->language)->title ?? $run->rule($ruleId)->help ?? $ruleId;
        $criteria = implode(', ', array_map(
            static fn(SuccessCriterion $c): string => $translator->trans('junit.criterion', ['%number%' => $c->number, '%level%' => $c->level->value]),
            $findings[0]->criteria,
        ));
        $failing = array_values(array_filter($findings, $options->isFailing(...)));

        $lines = [];
        foreach ($findings as $finding) {
            $state = $options->isFailing($finding) ? 'FAIL' : ($options->isKnown($finding->fingerprint) ? 'known' : 'info');
            $lines[] = \sprintf("[%s] %s %s\n%s", $state, $translator->trans('severity.' . $finding->severity->value), $finding->selector, $finding->summary);
        }

        return [
            'name' => $ruleId . ': ' . $title . ($criteria === '' ? '' : ' (' . $criteria . ')'),
            'classname' => $url,
            'result' => $failing === [] ? 'passed' : 'failure',
            'message' => \sprintf('%d × %s', \count($failing), $title),
            'body' => implode("\n\n", $lines) . "\n\n" . $findings[0]->helpUrl,
        ];
    }

    /** @param array{name: string, classname: string, result: string, message: string, body: string} $case */
    private function writeCase(XMLWriter $xml, array $case): void
    {
        $xml->startElement('testcase');
        $xml->writeAttribute('name', $case['name']);
        $xml->writeAttribute('classname', $case['classname']);

        switch ($case['result']) {
            case 'failure':
            case 'error':
                $xml->startElement($case['result']);
                $xml->writeAttribute('message', $case['message']);
                $xml->writeAttribute('type', $case['result'] === 'failure' ? 'accessibility' : 'page-not-audited');
                $xml->text($case['body']);
                $xml->endElement();
                break;
            case 'skipped':
                $xml->startElement('skipped');
                $xml->writeAttribute('message', $case['message']);
                $xml->endElement();
                break;
            case 'passed':
                if ($case['body'] !== '') {
                    $xml->writeElement('system-out', $case['body']);
                }
                break;
            default:
                throw new RuntimeException('Unknown test result ' . $case['result']);
        }

        $xml->endElement();
    }
}
