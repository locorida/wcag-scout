<?php

declare(strict_types=1);

namespace WcagScout\Report\Pdf;

interface PdfPrinter
{
    /** Prints a local HTML file to PDF. */
    public function printPdf(string $htmlFile, string $pdfFile): void;
}
