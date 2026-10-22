<?php

declare(strict_types=1);

namespace WcagScout\Report\Pdf;

use WcagScout\Model\AuditRun;
use WcagScout\Report\FileWriter;
use WcagScout\Report\Html\HtmlRenderer;
use WcagScout\Report\Renderer;
use WcagScout\Report\ReportOptions;

/**
 * The HTML report, printed by Chromium: same content, all details expanded.
 */
final readonly class PdfRenderer implements Renderer
{
    public const string FILE = 'report.pdf';

    public function __construct(
        private PdfPrinter $printer,
        private HtmlRenderer $html = new HtmlRenderer(),
    ) {}

    public function format(): string
    {
        return 'pdf';
    }

    public function render(AuditRun $run, ReportOptions $options, string $directory): string
    {
        // Next to the real report, so relative screenshot paths resolve.
        $printHtml = FileWriter::write($directory . '/.report.print.html', $this->html->html($run, $options, forPrint: true));
        try {
            $this->printer->printPdf($printHtml, $directory . '/' . self::FILE);
        } finally {
            @unlink($printHtml);
        }

        return $directory . '/' . self::FILE;
    }
}
