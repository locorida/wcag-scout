<?php

declare(strict_types=1);

namespace WcagScout\Report\Json;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use JsonException;
use WcagScout\Model\AuditRun;
use WcagScout\Model\Finding;
use WcagScout\Model\PageResult;
use WcagScout\Model\PageStatus;
use WcagScout\Model\RuleInfo;
use WcagScout\Model\Severity;
use WcagScout\Model\Url;
use WcagScout\Standards\WcagCatalog;

/**
 * Reads a JSON report written by RunSerializer back into an AuditRun.
 */
final readonly class RunDeserializer
{
    public function __construct(
        private WcagCatalog $catalog = new WcagCatalog(),
    ) {}

    public function fromFile(string $path): AuditRun
    {
        $json = is_file($path) ? file_get_contents($path) : false;
        if ($json === false) {
            throw new InvalidArgumentException(\sprintf('Cannot read "%s".', $path));
        }

        try {
            return $this->deserialize($json);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException(\sprintf('%s: %s', $path, $e->getMessage()), 0, $e);
        }
    }

    public function deserialize(string $json): AuditRun
    {
        try {
            $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Not valid JSON: ' . $e->getMessage(), 0, $e);
        }
        $data = Reader::of($data, 'report');

        $version = $data->int('schemaVersion');
        if ($version !== RunSerializer::SCHEMA_VERSION) {
            throw new InvalidArgumentException(\sprintf(
                'Unsupported report schema version %d (this version of wcag-scout reads %d).',
                $version,
                RunSerializer::SCHEMA_VERSION,
            ));
        }

        $rules = [];
        foreach ($data->list('rules', allowMap: true) as $rule) {
            $info = new RuleInfo(
                $rule->string('id'),
                $rule->string('engine'),
                $rule->string('help'),
                $rule->string('description'),
                $rule->string('helpUrl'),
                $rule->strings('tags'),
            );
            $rules[$info->id] = $info;
        }

        $engines = [];
        foreach ($data->map('engines') as $name => $engineVersion) {
            $engines[$name] = $engineVersion->asString();
        }

        $options = [];
        foreach ($data->map('options') as $name => $value) {
            $options[$name] = $value->asScalarOrList();
        }

        return new AuditRun(
            ($data->map('tool')['version'] ?? null)?->asString() ?? '',
            self::date($data->string('startedAt')),
            self::date($data->string('finishedAt')),
            $engines,
            $options,
            array_map($this->page(...), $data->list('pages')),
            $rules,
        );
    }

    private function page(Reader $page): PageResult
    {
        $url = Url::parse($page->string('url'));
        $status = PageStatus::tryFrom($page->string('status'))
            ?? throw new InvalidArgumentException('Unknown page status in ' . $page->path());

        return match ($status) {
            PageStatus::Audited => PageResult::audited(
                $url,
                $page->nullableString('title'),
                $page->nullableInt('httpStatus'),
                array_map(fn(Reader $f): Finding => $this->finding($f, $url), $page->list('findings')),
                $page->int('durationMs'),
            ),
            PageStatus::Skipped => PageResult::skipped($url, $page->nullableString('reason') ?? ''),
            PageStatus::Failed => PageResult::failed($url, $page->nullableString('reason') ?? '', $page->nullableInt('httpStatus'), $page->int('durationMs')),
        };
    }

    private function finding(Reader $finding, Url $url): Finding
    {
        $criteria = [];
        foreach ($finding->list('criteria') as $criterion) {
            $number = $criterion->string('number');
            if ($this->catalog->has($number)) {
                $criteria[] = $this->catalog->get($number);
            }
        }

        return new Finding(
            $finding->string('ruleId'),
            $finding->string('engine'),
            Severity::tryFrom($finding->string('severity'))
                ?? throw new InvalidArgumentException('Unknown severity in ' . $finding->path()),
            $criteria,
            $url,
            $finding->string('selector'),
            $finding->string('html'),
            $finding->string('summary'),
            $finding->string('helpUrl'),
            $finding->nullableString('screenshot'),
        );
    }

    private static function date(string $value): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value);
        } catch (Exception $e) {
            throw new InvalidArgumentException(\sprintf('Invalid date "%s".', $value), 0, $e);
        }
    }
}
