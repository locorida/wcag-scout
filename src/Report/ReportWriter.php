<?php

declare(strict_types=1);

namespace WcagScout\Report;

use InvalidArgumentException;
use WcagScout\Model\AuditRun;

/**
 * Writes the requested formats with the registered renderers.
 */
final readonly class ReportWriter
{
    /** @var array<string, Renderer> */
    private array $renderers;

    /** @param iterable<Renderer> $renderers */
    public function __construct(iterable $renderers)
    {
        $byFormat = [];
        foreach ($renderers as $renderer) {
            $byFormat[$renderer->format()] = $renderer;
        }
        $this->renderers = $byFormat;
    }

    /** @return list<string> */
    public function formats(): array
    {
        return array_keys($this->renderers);
    }

    /**
     * @param list<string> $formats
     * @return list<string> written files
     */
    public function write(AuditRun $run, ReportOptions $options, string $directory, array $formats): array
    {
        foreach ($formats as $format) {
            if (!isset($this->renderers[$format])) {
                throw new InvalidArgumentException(\sprintf('Unknown format "%s". Available: %s.', $format, implode(', ', $this->formats())));
            }
        }

        // JSON first: it is the result of record, and the other formats can be rebuilt from it.
        usort($formats, static fn(string $a, string $b): int => ($b === 'json') <=> ($a === 'json'));

        return array_map(fn(string $format): string => $this->renderers[$format]->render($run, $options, $directory), $formats);
    }
}
