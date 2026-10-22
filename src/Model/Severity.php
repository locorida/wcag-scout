<?php

declare(strict_types=1);

namespace WcagScout\Model;

use InvalidArgumentException;

/**
 * How badly a finding blocks users. Mirrors axe-core's "impact" levels.
 */
enum Severity: string
{
    case Critical = 'critical';
    case Serious = 'serious';
    case Moderate = 'moderate';
    case Minor = 'minor';

    /** axe-core reports no impact for some results; treat those as moderate. */
    public static function fromAxeImpact(?string $impact): self
    {
        return $impact === null ? self::Moderate : self::from($impact);
    }

    public static function fromOption(string $value): self
    {
        return self::tryFrom(strtolower($value))
            ?? throw new InvalidArgumentException(\sprintf(
                'Unknown severity "%s". Expected one of: %s.',
                $value,
                implode(', ', array_column(self::cases(), 'value')),
            ));
    }

    public function rank(): int
    {
        return match ($this) {
            self::Critical => 4,
            self::Serious => 3,
            self::Moderate => 2,
            self::Minor => 1,
        };
    }

    public function isAtLeast(self $threshold): bool
    {
        return $this->rank() >= $threshold->rank();
    }

    /** @return list<self> most severe first */
    public static function ordered(): array
    {
        return [self::Critical, self::Serious, self::Moderate, self::Minor];
    }
}
