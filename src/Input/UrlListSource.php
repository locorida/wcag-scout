<?php

declare(strict_types=1);

namespace WcagScout\Input;

use InvalidArgumentException;
use RuntimeException;
use WcagScout\Model\Url;

/**
 * URLs given on the command line or in a text file (one per line, "#" starts a comment).
 */
final readonly class UrlListSource implements UrlSource
{
    /** @param list<Url> $urls */
    private function __construct(private array $urls) {}

    /** @param list<string> $urls */
    public static function fromStrings(array $urls): self
    {
        return new self(array_map(Url::parse(...), $urls));
    }

    public static function fromFile(string $path): self
    {
        $content = is_file($path) ? file_get_contents($path) : false;
        if ($content === false) {
            throw new RuntimeException(\sprintf('Cannot read URL list "%s".', $path));
        }

        $urls = [];
        $lines = preg_split('/\R/', $content);
        foreach ($lines === false ? [] : $lines as $number => $line) {
            $line = trim(explode('#', $line, 2)[0]);
            if ($line === '') {
                continue;
            }
            try {
                $urls[] = Url::parse($line);
            } catch (InvalidArgumentException $e) {
                throw new InvalidArgumentException(\sprintf('%s, line %d: %s', $path, $number + 1, $e->getMessage()), 0, $e);
            }
        }

        return new self($urls);
    }

    public function urls(): iterable
    {
        yield from $this->urls;
    }
}
