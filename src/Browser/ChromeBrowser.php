<?php

declare(strict_types=1);

namespace WcagScout\Browser;

use Exception;
use HeadlessChromium\Browser;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Communication\Message;
use WcagScout\Crawl\UserAgent;
use WcagScout\Model\Url;

/**
 * One headless Chromium for the whole run; every URL gets a fresh tab.
 */
final class ChromeBrowser implements PageLoader
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
