<?php

declare(strict_types=1);

namespace WcagScout\Standards;

use InvalidArgumentException;

/**
 * Versions of EN 301 549 whose chapter 9 (web content) mirrors WCAG levels A and AA:
 * WCAG x.y.z becomes clause 9.x.y.z.
 *
 * - V3.2.1 (2021) is based on WCAG 2.1. It is the version cited in the Official Journal of
 *   the EU and therefore the legal reference for the Web Accessibility Directive and the
 *   European Accessibility Act (in Germany: BFSG) until a newer version is cited.
 * - V4.1.1 (published by ETSI in September 2026) is based on WCAG 2.2: it adds the six new
 *   A/AA criteria and marks 4.1.1 Parsing as void.
 */
enum En301549Version: string
{
    case V3_2_1 = '3.2.1';
    case V4_1_1 = '4.1.1';

    public const self DEFAULT = self::V3_2_1;

    public static function fromOption(string $value): self
    {
        return self::tryFrom(ltrim(strtolower($value), 'v'))
            ?? throw new InvalidArgumentException(\sprintf(
                'Unknown EN 301 549 version "%s". Expected one of: %s.',
                $value,
                implode(', ', array_column(self::cases(), 'value')),
            ));
    }

    /** The clause covering $criterion, or null if this version has none. */
    public function clauseFor(SuccessCriterion $criterion): ?string
    {
        if (!$criterion->level->isWithin(Level::AA)) {
            return null;
        }
        if ($this === self::V3_2_1 && $criterion->since === '2.2') {
            return null;
        }

        return '9.' . $criterion->number;
    }

    public function label(): string
    {
        return 'EN 301 549 V' . $this->value;
    }
}
