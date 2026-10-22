<?php

declare(strict_types=1);

namespace WcagScout\Browser;

use Exception;
use HeadlessChromium\Browser;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Communication\Message;
use HeadlessChromium\Page;
use WcagScout\Crawl\UserAgent;
use WcagScout\Model\Url;
use WcagScout\Report\Pdf\PdfPrinter;

/**
 * One headless Chromium for the whole run; every URL gets a fresh tab.
 */
final class ChromeBrowser implements PageLoader, PdfPrinter
{
    private ?Browser $browser = null;
    private ?string $userAgent = null;

    public function __construct(
        private readonly UserAgent $identity,
        private readonly BrowserOptions $options = new BrowserOptions(),
        private readonly RequestGuard $guard = new RequestGuard(),
    ) {}

    /**
     * Opens $url in a new tab and waits until it has loaded and settled.
     *
     * @throws BrowserException
     */
    public function open(Url $url): BrowserPage
    {
        try {
            $browser = $this->browser();
            $page = $browser->createPage();
        } catch (Exception $e) {
            throw new BrowserException('Could not start Chromium: ' . $e->getMessage(), 0, $e);
        }

        return ChromePage::load($page, $url, $this->userAgent ?? '', $this->guard, $this->options);
    }

    /**
     * Prints a local HTML file to PDF. Uses Page.printToPDF directly, because chrome-php does
     * not pass on generateTaggedPDF: the tag tree is what makes the PDF readable for screen
     * readers, and the outline turns headings into bookmarks.
     *
     * @throws BrowserException
     */
    public function printPdf(string $htmlFile, string $pdfFile): void
    {
        try {
            $page = $this->browser()->createPage();
        } catch (Exception $e) {
            throw new BrowserException('Could not start Chromium: ' . $e->getMessage(), 0, $e);
        }

        try {
            $page->navigate('file://' . realpath($htmlFile))->waitForNavigation(Page::LOAD, $this->options->timeoutMs);
            $response = $page->getSession()->sendMessageSync(new Message('Page.printToPDF', [
                'printBackground' => true,
                'preferCSSPageSize' => true,
                'generateTaggedPDF' => true,
                'generateDocumentOutline' => true,
                'displayHeaderFooter' => true,
                'headerTemplate' => '<span></span>',
                'footerTemplate' => '<div style="width:100%;font:8px sans-serif;text-align:center;color:#444">'
                    . '<span class="pageNumber"></span> / <span class="totalPages"></span></div>',
            ]), 120_000);
            if (!$response->isSuccessful()) {
                throw new BrowserException('PDF export failed: ' . $response->getErrorMessage());
            }
            $data = $response->getResultData('data');
            $pdf = \is_string($data) ? base64_decode($data, true) : false;
            if ($pdf === false || file_put_contents($pdfFile, $pdf) === false) {
                throw new BrowserException('Chromium returned no PDF data.');
            }
        } catch (BrowserException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new BrowserException('PDF export failed: ' . $e->getMessage(), 0, $e);
        } finally {
            try {
                $page->close();
            } catch (Exception) {
                // Already closed.
            }
        }
    }

    public function version(): string
    {
        $version = $this->browserInfo()['product'] ?? '';

        return \is_string($version) ? $version : '';
    }

    public function close(): void
    {
        try {
            $this->browser?->close();
        } catch (Exception) {
            // Closing a browser that already crashed must not hide the real error.
        }
        $this->browser = null;
    }

    public function __destruct()
    {
        $this->close();
    }

    private function browser(): Browser
    {
        if ($this->browser !== null) {
            return $this->browser;
        }

        $factory = new BrowserFactory($this->options->chromePath);
        $this->browser = $factory->createBrowser([
            'headless' => true,
            'noSandbox' => !$this->options->sandbox,
            'windowSize' => [$this->options->viewportWidth, $this->options->viewportHeight],
            'sendSyncDefaultTimeout' => $this->options->timeoutMs,
            'startupTimeout' => 30,
            'customFlags' => [
                // Docker's /dev/shm is 64 MB; Chromium crashes on large pages without this.
                '--disable-dev-shm-usage',
                // No traffic of Chromium's own (component updates, safe browsing, …).
                '--disable-background-networking',
                '--disable-component-update',
                '--mute-audio',
                '--hide-scrollbars',
            ],
        ]);

        $browserUserAgent = $this->browserInfo()['userAgent'] ?? '';
        $this->userAgent = $this->identity->forBrowser(\is_string($browserUserAgent) ? $browserUserAgent : '');

        return $this->browser;
    }

    /** @return array<mixed> */
    private function browserInfo(): array
    {
        try {
            $data = $this->browser()->getConnection()->sendMessageSync(new Message('Browser.getVersion'))->getData();
        } catch (Exception) {
            return [];
        }
        $result = $data['result'] ?? [];

        return \is_array($result) ? $result : [];
    }
}
