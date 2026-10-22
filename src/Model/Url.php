<?php

declare(strict_types=1);

namespace WcagScout\Model;

use InvalidArgumentException;
use Stringable;

/**
 * Normalised absolute http(s) URL.
 *
 * Normalisation (RFC 3986, section 6): lower-case scheme and host, default port removed,
 * dot segments resolved, empty path becomes "/", fragment dropped. The query string is kept
 * verbatim, because parameter order can matter to the server.
 */
final readonly class Url implements Stringable
{
    private function __construct(
        public string $scheme,
        public string $host,
        public ?int $port,
        public string $path,
        public ?string $query,
    ) {}

    public static function parse(string $url): self
    {
        $parts = parse_url(trim($url));
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new InvalidArgumentException(\sprintf('"%s" is not an absolute URL.', $url));
        }

        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new InvalidArgumentException(\sprintf('Only http and https URLs are supported, got "%s".', $url));
        }

        $port = $parts['port'] ?? null;
        if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
            $port = null;
        }

        return new self(
            $scheme,
            strtolower($parts['host']),
            $port,
            self::removeDotSegments(($parts['path'] ?? '') === '' ? '/' : $parts['path']),
            $parts['query'] ?? null,
        );
    }

    /** Resolves a (possibly relative) reference against this URL, RFC 3986 section 5.2. */
    public function resolve(string $reference): self
    {
        $reference = trim($reference);
        $fragmentless = strstr($reference, '#', true);
        $reference = $fragmentless === false ? $reference : $fragmentless;

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $reference) === 1) {
            return self::parse($reference);
        }
        if (str_starts_with($reference, '//')) {
            return self::parse($this->scheme . ':' . $reference);
        }
        if ($reference === '') {
            return $this;
        }

        $queryPos = strpos($reference, '?');
        $refPath = $queryPos === false ? $reference : substr($reference, 0, $queryPos);
        $refQuery = $queryPos === false ? null : substr($reference, $queryPos + 1);

        if ($refPath === '') {
            return new self($this->scheme, $this->host, $this->port, $this->path, $refQuery ?? $this->query);
        }

        $path = str_starts_with($refPath, '/')
            ? $refPath
            : substr($this->path, 0, (int) strrpos($this->path, '/') + 1) . $refPath;

        return new self($this->scheme, $this->host, $this->port, self::removeDotSegments($path), $refQuery);
    }

    /** scheme://host[:port], used to keep a crawl on the site it started on. */
    public function origin(): string
    {
        return $this->scheme . '://' . $this->host . ($this->port === null ? '' : ':' . $this->port);
    }

    public function isSameOrigin(self $other): bool
    {
        return $this->origin() === $other->origin();
    }

    /** Path plus query, as matched by robots.txt rules. */
    public function pathAndQuery(): string
    {
        return $this->path . ($this->query === null ? '' : '?' . $this->query);
    }

    public function equals(self $other): bool
    {
        return (string) $this === (string) $other;
    }

    public function __toString(): string
    {
        return $this->origin() . $this->pathAndQuery();
    }

    private static function removeDotSegments(string $path): string
    {
        $segments = explode('/', $path);
        $output = [];
        foreach ($segments as $segment) {
            if ($segment === '..') {
                if (\count($output) > 1) {
                    array_pop($output);
                }
            } elseif ($segment !== '.') {
                $output[] = $segment;
            }
        }
        // A trailing "." or ".." refers to a directory, so the result keeps its trailing slash.
        if (\in_array(end($segments), ['.', '..'], true)) {
            $output[] = '';
        }

        $result = implode('/', $output);

        return str_starts_with($result, '/') ? $result : '/' . $result;
    }
}
