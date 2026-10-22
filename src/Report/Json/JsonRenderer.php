<?php

declare(strict_types=1);

namespace WcagScout\Report\Json;

use WcagScout\Model\AuditRun;
use WcagScout\Report\FileWriter;
use WcagScout\Report\Renderer;
use WcagScout\Report\ReportOptions;

final readonly class JsonRenderer implements Renderer
{
    public const string FILE = 'report.json';

    public function __construct(
        private RunSerializer $serializer = new RunSerializer(),
    ) {}

    public function format(): string
    {
        return 'json';
    }

    public function render(AuditRun $run, ReportOptions $options, string $directory): string
    {
        return FileWriter::write($directory . '/' . self::FILE, $this->serializer->serialize($run));
    }
}
