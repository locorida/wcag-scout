<?php

declare(strict_types=1);

namespace WcagScout\Cli;

use WcagScout\Report\Html\HtmlRenderer;
use WcagScout\Report\Json\JsonRenderer;
use WcagScout\Report\JUnit\JUnitRenderer;
use WcagScout\Report\Pdf\PdfPrinter;
use WcagScout\Report\Pdf\PdfRenderer;
use WcagScout\Report\ReportWriter;

/**
 * The set of available report formats. The one place to register a new renderer.
 */
final class Reports
{
    public static function writer(PdfPrinter $printer): ReportWriter
    {
        $html = new HtmlRenderer();

        return new ReportWriter([
            new JsonRenderer(),
            $html,
            new PdfRenderer($printer, $html),
            new JUnitRenderer(),
        ]);
    }
}
