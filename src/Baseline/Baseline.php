<?php

declare(strict_types=1);

namespace WcagScout\Baseline;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use InvalidArgumentException;
use JsonException;
use WcagScout\Model\AuditRun;
use WcagScout\Report\FileWriter;
use WcagScout\Report\Json\Reader;

/**
 * Findings that are known and accepted for now. With --baseline, only findings that are not
 * in it fail a run, so a CI pipeline can be introduced on a site with existing problems and
 * still catch every new one.
 *
 * The file lists rule, page and selector next to each fingerprint, so a reviewer can see in a
 * pull request what is being accepted.
 */
final readonly class Baseline
{
    public const int SCHEMA_VERSION = 1;

    /** @param list<array{fingerprint: string, ruleId: string, url: string, selector: string}> $entries */
    private function __construct(
        public array $entries,
        public DateTimeImmutable $createdAt,
    ) {}

    public static function fromRun(AuditRun $run, DateTimeImmutable $createdAt = new DateTimeImmutable()): self
    {
        $entries = [];
        foreach ($run->findings() as $finding) {
            $entries[(string) $finding->fingerprint] = [
                'fingerprint' => (string) $finding->fingerprint,
                'ruleId' => $finding->ruleId,
                'url' => (string) $finding->pageUrl,
                'selector' => $finding->selector,
            ];
        }
        // Stable order keeps diffs of the baseline file small.
        uasort($entries, static fn(array $a, array $b): int => [$a['url'], $a['ruleId'], $a['selector']] <=> [$b['url'], $b['ruleId'], $b['selector']]);

        return new self(array_values($entries), $createdAt);
    }

    public static function fromFile(string $path): self
    {
        $json = is_file($path) ? file_get_contents($path) : false;
        if ($json === false) {
            throw new InvalidArgumentException(\sprintf('Cannot read baseline "%s".', $path));
        }
        try {
            $data = Reader::of(json_decode($json, true, 16, JSON_THROW_ON_ERROR), 'baseline');
            if ($data->int('schemaVersion') !== self::SCHEMA_VERSION) {
                throw new InvalidArgumentException('Unsupported baseline schema version.');
            }
            $entries = array_map(static fn(Reader $e): array => [
                'fingerprint' => $e->string('fingerprint'),
                'ruleId' => $e->string('ruleId'),
                'url' => $e->string('url'),
                'selector' => $e->string('selector'),
            ], $data->list('findings'));

            return new self($entries, new DateTimeImmutable($data->string('createdAt')));
        } catch (JsonException $e) {
            throw new InvalidArgumentException(\sprintf('%s is not valid JSON: %s', $path, $e->getMessage()), 0, $e);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException(\sprintf('%s: %s', $path, $e->getMessage()), 0, $e);
        } catch (Exception $e) {
            throw new InvalidArgumentException(\sprintf('%s: invalid createdAt.', $path), 0, $e);
        }
    }

    /** @return array<string, true> */
    public function fingerprints(): array
    {
        return array_fill_keys(array_column($this->entries, 'fingerprint'), true);
    }

    public function save(string $path): string
    {
        return FileWriter::write($path, json_encode([
            'schemaVersion' => self::SCHEMA_VERSION,
            'createdAt' => $this->createdAt->format(DateTimeInterface::ATOM),
            'note' => 'Known accessibility findings. With --baseline, only findings not listed here fail a run.',
            'findings' => $this->entries,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
    }
}
