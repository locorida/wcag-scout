<?php

declare(strict_types=1);

namespace WcagScout\Report;

use WcagScout\Model\AuditRun;

/**
 * Writes one report format. New formats (e.g. SARIF) implement this interface.
 */
interface Renderer
{
    /** Short name used on the command line, e.g. "html". */
    public function format(): string;

    /** @return string path of the written file */
    public function render(AuditRun $run, ReportOptions $options, string $directory): string;
}
