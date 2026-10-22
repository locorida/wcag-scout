<?php

declare(strict_types=1);

namespace WcagScout\Standards;

/**
 * WCAG conformance level. Ordered: a page conforming to AA also meets every A criterion.
 */
enum Level: string
{
    case A = 'A';
    case AA = 'AA';
    case AAA = 'AAA';

    public function rank(): int
    {
        return match ($this) {
            self::A => 1,
            self::AA => 2,
            self::AAA => 3,
        };
    }

    /** Whether a criterion of this level is required when targeting $target. */
    public function isWithin(self $target): bool
    {
        return $this->rank() <= $target->rank();
    }
}
