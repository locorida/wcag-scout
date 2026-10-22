<?php

declare(strict_types=1);

namespace WcagScout\Browser;

use Exception;
use HeadlessChromium\Clip;
use HeadlessChromium\Communication\Message;
use HeadlessChromium\Exception\OperationTimedOut;
use HeadlessChromium\Page;
use WcagScout\Model\Url;

/**
 * @internal created by {@see ChromeBrowser}
 */
final class ChromePage implements BrowserPage
{
    private const string HIGHLIGHT_ATTRIBUTE = 'data-wcag-scout-highlight';

    private bool $frozen = false;
    private ?NavigationInfo $info = null;

    /** @var array{status: int, mimeType: string, url: string}|null last main-frame document response */
    private ?array $documentResponse = null;

    private function __construct(
        private readonly Page $page,
        private readonly RequestGuard $guard,
        private readonly BrowserOptions $options,
    ) {}

    /** @throws BrowserException */
    public static function load(Page $page, Url $url, string $userAgent, RequestGuard $guard, BrowserOptions $options): self
    {
        $self = new self($page, $guard, $options);
        try {
            $self->intercept($userAgent);
            $self->navigate($url);
        } catch (BrowserException $e) {
            $self->close();

            throw $e;
        } catch (Exception $e) {
            $self->close();

            throw new BrowserException($e->getMessage(), 0, $e);
        }

        return $self;
    }

    public function info(): NavigationInfo
    {
        return $this->info ?? throw new BrowserException('Page has not been loaded.');
    }

    public function evaluate(string $expression, int $timeoutMs = 30_000): mixed
    {
        try {
            return $this->page->evaluate($expression)->getReturnValue($timeoutMs);
        } catch (Exception $e) {
            throw new BrowserException('Script evaluation failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function screenshotElement(string $selector, string $path): bool
    {
        $selectorJson = json_encode($selector, JSON_THROW_ON_ERROR);
        $attribute = self::HIGHLIGHT_ATTRIBUTE;
        $box = $this->evaluate(<<<JS
            (() => {
                let element;
                try { element = document.querySelector({$selectorJson}); } catch (e) { return null; }
                if (!element) return null;
                element.scrollIntoView({block: 'center', inline: 'center'});
                const rect = element.getBoundingClientRect();
                if (rect.width < 1 || rect.height < 1) return null;
                if (!document.getElementById('{$attribute}-style')) {
                    const style = document.createElement('style');
                    style.id = '{$attribute}-style';
                    style.textContent = '[{$attribute}] { outline: 4px solid #c8102e !important; outline-offset: 3px !important; }';
                    document.head.appendChild(style);
                }
                element.setAttribute('{$attribute}', '');
                const root = document.documentElement;
                return {
                    x: rect.left + window.scrollX, y: rect.top + window.scrollY,
                    width: rect.width, height: rect.height,
                    pageWidth: Math.max(root.scrollWidth, window.innerWidth),
                    pageHeight: Math.max(root.scrollHeight, window.innerHeight),
                };
            })()
            JS);
        if (!\is_array($box)) {
            return false;
        }

        $clip = ScreenshotClip::around(
            self::number($box['x'] ?? 0),
            self::number($box['y'] ?? 0),
            self::number($box['width'] ?? 0),
            self::number($box['height'] ?? 0),
            self::number($box['pageWidth'] ?? 0),
            self::number($box['pageHeight'] ?? 0),
        );

        try {
            $this->page
                ->screenshot([
                    'format' => 'png',
                    'clip' => new Clip($clip->x, $clip->y, $clip->width, $clip->height),
                    'captureBeyondViewport' => true,
                ])
                ->saveToFile($path, $this->options->timeoutMs);
        } catch (Exception) {
            return false;
        } finally {
            $this->evaluate("document.querySelectorAll('[{$attribute}]').forEach(e => e.removeAttribute('{$attribute}'))");
        }

        return true;
    }

    public function links(): array
    {
        $links = $this->evaluate(<<<'JS'
            Array.from(document.querySelectorAll('a[href]'))
                .filter(a => !a.hasAttribute('download'))
                .map(a => a.href)
                .filter(href => /^https?:/i.test(href))
            JS);

        return \is_array($links) ? array_values(array_filter($links, \is_string(...))) : [];
    }

    public function close(): void
    {
        try {
            $this->page->close();
        } catch (Exception) {
            // The tab may already be gone after a crash.
        }
    }

    /** Routes every request through the RequestGuard and records the main document's response. */
    private function intercept(string $userAgent): void
    {
        $session = $this->page->getSession();
        $mainFrameId = $this->page->getFrameManager()->getMainFrame()->getFrameId();

        $session->on('method:Fetch.requestPaused', function (array $params) use ($session, $mainFrameId): void {
            $request = \is_array($params['request'] ?? null) ? $params['request'] : [];
            $decision = $this->guard->decide(
                self::string($request['method'] ?? 'GET'),
                self::string($params['resourceType'] ?? ''),
                ($params['frameId'] ?? null) === $mainFrameId,
                $this->frozen,
            );
            $reason = $decision->cdpErrorReason();
            $session->sendMessage($reason === null
                ? new Message('Fetch.continueRequest', ['requestId' => $params['requestId'] ?? ''])
                : new Message('Fetch.failRequest', ['requestId' => $params['requestId'] ?? '', 'errorReason' => $reason]));
        });

        $session->on('method:Network.responseReceived', function (array $params) use ($mainFrameId): void {
            if ($this->frozen || ($params['type'] ?? '') !== 'Document' || ($params['frameId'] ?? null) !== $mainFrameId) {
                return;
            }
            $response = \is_array($params['response'] ?? null) ? $params['response'] : [];
            $status = $response['status'] ?? 0;
            $this->documentResponse = [
                'status' => \is_int($status) || \is_float($status) ? (int) $status : 0,
                'mimeType' => self::string($response['mimeType'] ?? ''),
                'url' => self::string($response['url'] ?? ''),
            ];
        });

        $session->sendMessageSync(new Message('Network.enable'));
        $session->sendMessageSync(new Message('Fetch.enable', ['patterns' => [['urlPattern' => '*']]]));
        if ($userAgent !== '') {
            $this->page->setUserAgent($userAgent);
        }
    }

    private function navigate(Url $url): void
    {
        $timedOut = false;
        try {
            $this->page->navigate((string) $url)->waitForNavigation(Page::LOAD, $this->options->timeoutMs);
        } catch (OperationTimedOut) {
            // Slow third-party resources can delay the load event for a long time. If the
            // document itself is there, audit it as it is and say so in the report.
            $timedOut = true;
        }
        $this->frozen = true;

        if ($timedOut && $this->evaluate('document.readyState') === 'loading') {
            throw new BrowserException(\sprintf('Page did not load within %d seconds.', intdiv($this->options->timeoutMs, 1000)));
        }

        // Give late scripts and web fonts a moment; fonts affect contrast and layout checks.
        $this->evaluate(\sprintf(
            'Promise.all([document.fonts.ready, new Promise(r => setTimeout(r, %d))]).then(() => true)',
            $this->options->settleMs,
        ));

        $title = $this->evaluate('document.title');
        $this->info = new NavigationInfo(
            $this->documentResponse['url'] ?? self::string($this->evaluate('location.href')),
            $this->documentResponse['status'] ?? null,
            $this->documentResponse['mimeType'] ?? null,
            \is_string($title) && trim($title) !== '' ? trim($title) : null,
            $timedOut,
        );
    }

    private static function string(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }

    private static function number(mixed $value): float
    {
        return \is_int($value) || \is_float($value) ? (float) $value : 0.0;
    }
}
