<?php

declare(strict_types=1);

namespace WcagScout\Engine;

use InvalidArgumentException;
use WcagScout\Standards\Level;

final readonly class AuditOptions
{
    public const array LANGUAGES = ['en', 'de'];

    public function __construct(
        /** Highest WCAG level to test. */
        public Level $level = Level::AA,
        /** Also run rules that are good practice but not required by WCAG. */
        public bool $bestPractices = true,
        /** Language of the engine's messages. */
        public string $language = 'en',
    ) {
        if (!\in_array($language, self::LANGUAGES, true)) {
            throw new InvalidArgumentException(\sprintf('Unsupported language "%s". Expected one of: %s.', $language, implode(', ', self::LANGUAGES)));
        }
    }
}
