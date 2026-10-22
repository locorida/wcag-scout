<?php

declare(strict_types=1);

namespace WcagScout\Crawl;

/**
 * Parsed robots.txt following RFC 9309 (Robots Exclusion Protocol).
 *
 * - Groups start with one or more "user-agent" lines. All groups whose user-agent matches our
 *   product token are combined; if none matches, the "*" groups apply; if there are none,
 *   everything is allowed.
 * - The rule with the longest matching path wins. On a tie, "allow" wins.
 * - "*" matches any sequence of characters, "$" anchors the end of the path.
 * - "/robots.txt" itself is always allowed.
 * - "crawl-delay" (not part of RFC 9309, but widely used) is read per group.
 */
final readonly class RobotsTxt
{
    /** Parsers must handle at least 500 KiB (RFC 9309, section 2.5); anything beyond is ignored. */
    public const int MAX_BYTES = 512_000;

    /**
     * @param list<array{agents: list<string>, rules: list<array{allow: bool, pattern: string}>, crawlDelay: float|null}> $groups
     * @param list<string> $sitemaps
     */
    private function __construct(
        private array $groups,
        public array $sitemaps,
        private bool $disallowAll = false,
    ) {}

    public static function parse(string $content): self
    {
        $content = substr($content, 0, self::MAX_BYTES);
        // A UTF-8 byte order mark at the start is allowed.
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        $groups = [];
        $sitemaps = [];
        $current = null;
        $lastWasAgent = false;

        $lines = preg_split('/\r\n|\r|\n/', $content);
        foreach ($lines === false ? [] : $lines as $line) {
            $line = trim(explode('#', $line, 2)[0]);
            if (!str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = array_map(trim(...), explode(':', $line, 2));
            $key = strtolower($key);

            switch ($key) {
                case 'user-agent':
                    if (!$lastWasAgent || $current === null) {
                        if ($current !== null) {
                            $groups[] = $current;
                        }
                        $current = ['agents' => [], 'rules' => [], 'crawlDelay' => null];
                    }
                    // The value should be a bare product token; tolerate "Bot/2.1" and trailing comments.
                    $current['agents'][] = strtolower(preg_split('#[/\s]#', $value)[0] ?? $value);
                    $lastWasAgent = true;
                    break;

                case 'allow':
                case 'disallow':
                    $lastWasAgent = false;
                    // Rules before the first user-agent line belong to no group.
                    if ($current === null || $value === '') {
                        break;
                    }
                    $current['rules'][] = ['allow' => $key === 'allow', 'pattern' => $value];
                    break;

                case 'crawl-delay':
                    $lastWasAgent = false;
                    if ($current !== null && is_numeric($value) && (float) $value >= 0) {
                        $current['crawlDelay'] = (float) $value;
                    }
                    break;

                case 'sitemap':
                    // Sitemap lines are independent of groups.
                    if ($value !== '') {
                        $sitemaps[] = $value;
                    }
                    break;

                default:
                    // Unknown keys do not end a user-agent block (RFC 9309 is silent; Google does the same).
                    break;
            }
        }
        if ($current !== null) {
            $groups[] = $current;
        }

        return new self($groups, $sitemaps);
    }

    /** No robots.txt (HTTP 4xx): everything is allowed. RFC 9309, section 2.3.1.3. */
    public static function allowAll(): self
    {
        return new self([], []);
    }

    /** robots.txt unreachable (HTTP 5xx, network error): assume complete disallow. Section 2.3.1.4. */
    public static function disallowAll(): self
    {
        return new self([], [], true);
    }

    public function isAllowed(string $pathAndQuery, string $productToken): bool
    {
        if ($pathAndQuery === '/robots.txt') {
            return true;
        }
        if ($this->disallowAll) {
            return false;
        }

        $best = null;
        foreach ($this->rulesFor($productToken) as $rule) {
            if (!self::matches($rule['pattern'], $pathAndQuery)) {
                continue;
            }
            $length = \strlen($rule['pattern']);
            if ($best === null || $length > $best['length'] || ($length === $best['length'] && $rule['allow'])) {
                $best = ['length' => $length, 'allow' => $rule['allow']];
            }
        }

        return $best === null || $best['allow'];
    }

    public function crawlDelay(string $productToken): ?float
    {
        $delays = array_filter(
            array_column($this->groupsFor($productToken), 'crawlDelay'),
            static fn(?float $delay): bool => $delay !== null,
        );

        return $delays === [] ? null : max($delays);
    }

    /** @return list<array{allow: bool, pattern: string}> */
    private function rulesFor(string $productToken): array
    {
        return array_merge(...array_column($this->groupsFor($productToken), 'rules'));
    }

    /** @return list<array{agents: list<string>, rules: list<array{allow: bool, pattern: string}>, crawlDelay: float|null}> */
    private function groupsFor(string $productToken): array
    {
        $token = strtolower($productToken);
        $specific = array_values(array_filter(
            $this->groups,
            static fn(array $group): bool => \in_array($token, $group['agents'], true),
        ));
        if ($specific !== []) {
            return $specific;
        }

        return array_values(array_filter(
            $this->groups,
            static fn(array $group): bool => \in_array('*', $group['agents'], true),
        ));
    }

    private static function matches(string $pattern, string $path): bool
    {
        $anchored = str_ends_with($pattern, '$');
        if ($anchored) {
            $pattern = substr($pattern, 0, -1);
        }
        $regex = implode('.*', array_map(
            static fn(string $part): string => preg_quote(self::normalisePercentEncoding($part), '#'),
            explode('*', $pattern),
        ));

        return preg_match('#^' . $regex . ($anchored ? '$' : '') . '#', self::normalisePercentEncoding($path)) === 1;
    }

    /**
     * Compares percent-encoded and literal forms alike (RFC 9309, section 2.2.2):
     * "%7E" and "~" match each other; reserved characters such as "%2F" stay encoded.
     */
    private static function normalisePercentEncoding(string $value): string
    {
        return preg_replace_callback(
            '/%([0-9A-Fa-f]{2})/',
            static function (array $m): string {
                $char = \chr((int) hexdec($m[1]) & 0xFF);

                return preg_match('/[A-Za-z0-9\-._~]/', $char) === 1 ? $char : '%' . strtoupper($m[1]);
            },
            $value,
        ) ?? $value;
    }
}
