<?php

declare(strict_types=1);

namespace WcagScout\Input;

use RuntimeException;
use ValueError;
use XMLReader;

/**
 * A parsed sitemap file: either a list of page URLs (<urlset>) or a list of further
 * sitemaps (<sitemapindex>). See https://www.sitemaps.org/protocol.html.
 */
final readonly class Sitemap
{
    /** Protocol limit for one (uncompressed) sitemap file. */
    public const int MAX_BYTES = 52_428_800;

    /** @param list<string> $locations */
    private function __construct(
        public bool $isIndex,
        public array $locations,
    ) {}

    /**
     * @param string $content raw response body; gzip-compressed files are detected by their magic bytes
     */
    public static function parse(string $content): self
    {
        if (str_starts_with($content, "\x1f\x8b")) {
            // The length limit guards against decompression bombs.
            $decoded = @gzdecode($content, self::MAX_BYTES);
            if ($decoded === false) {
                throw new RuntimeException('Invalid gzip data or sitemap larger than 50 MB.');
            }
            $content = $decoded;
        }
        if (\strlen($content) > self::MAX_BYTES) {
            throw new RuntimeException('Sitemap larger than 50 MB.');
        }

        try {
            // No network access while parsing; entities are not substituted (no XXE).
            $reader = XMLReader::fromString($content, null, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        } catch (ValueError $e) {
            throw new RuntimeException('Sitemap is empty or not valid XML.', 0, $e);
        }

        $root = null;
        $locations = [];
        $previous = libxml_use_internal_errors(true);
        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT) {
                    continue;
                }
                $root ??= $reader->localName;
                if ($reader->localName === 'loc') {
                    $location = trim($reader->readString());
                    if ($location !== '') {
                        $locations[] = $location;
                    }
                }
            }
            $errors = libxml_get_errors();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $reader->close();
        }

        if ($errors !== [] || ($root !== 'urlset' && $root !== 'sitemapindex')) {
            throw new RuntimeException('Not a sitemap: expected <urlset> or <sitemapindex> as root element.');
        }

        return new self($root === 'sitemapindex', $locations);
    }
}
