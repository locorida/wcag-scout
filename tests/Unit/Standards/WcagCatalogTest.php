<?php

declare(strict_types=1);

namespace WcagScout\Tests\Unit\Standards;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WcagScout\Standards\En301549Version;
use WcagScout\Standards\Level;
use WcagScout\Standards\SuccessCriterion;
use WcagScout\Standards\WcagCatalog;

final class WcagCatalogTest extends TestCase
{
    private WcagCatalog $catalog;

    protected function setUp(): void
    {
        $this->catalog = new WcagCatalog();
    }

    public function testContainsAllWcag22CriteriaPerLevel(): void
    {
        $perLevel = array_count_values(array_map(
            static fn(SuccessCriterion $c): string => $c->level->value,
            $this->catalog->all(),
        ));

        self::assertSame(['A' => 31, 'AA' => 24, 'AAA' => 31], [
            'A' => $perLevel['A'] ?? 0,
            'AA' => $perLevel['AA'] ?? 0,
            'AAA' => $perLevel['AAA'] ?? 0,
        ]);
        self::assertFalse($this->catalog->has('4.1.1'), '4.1.1 Parsing is obsolete in WCAG 2.2');
    }

    /** @return iterable<string, array{string, string|null, string|null}> criterion, clause in V3.2.1, clause in V4.1.1 */
    public static function en301549Cases(): iterable
    {
        yield 'WCAG 2.0, level A' => ['1.1.1', '9.1.1.1', '9.1.1.1'];
        yield 'WCAG 2.0, level AA' => ['1.4.3', '9.1.4.3', '9.1.4.3'];
        yield 'WCAG 2.1, level AA' => ['1.4.11', '9.1.4.11', '9.1.4.11'];
        yield 'WCAG 2.2, level AA: only in V4.1.1' => ['2.5.8', null, '9.2.5.8'];
        yield 'WCAG 2.2, level A: only in V4.1.1' => ['3.2.6', null, '9.3.2.6'];
        yield 'AAA: in no version' => ['1.4.6', null, null];
        yield 'WCAG 2.2 AAA: in no version' => ['2.4.13', null, null];
    }

    #[DataProvider('en301549Cases')]
    public function testMapsToEn301549Clause(string $number, ?string $v321, ?string $v411): void
    {
        $criterion = $this->catalog->get($number);

        self::assertSame($v321, $criterion->en301549Clause(En301549Version::V3_2_1));
        self::assertSame($v411, $criterion->en301549Clause(En301549Version::V4_1_1));
    }

    public function testDefaultsToTheVersionCitedInTheOfficialJournal(): void
    {
        self::assertNull($this->catalog->get('2.5.8')->en301549Clause(), 'default must be V3.2.1, which has no 2.5.8');
        self::assertSame(En301549Version::V4_1_1, En301549Version::fromOption('v4.1.1'));
        self::assertSame('EN 301 549 V3.2.1', En301549Version::V3_2_1->label());
    }

    public function testParsesAxeTags(): void
    {
        $criteria = $this->catalog->fromAxeTags(['cat.color', 'wcag2aa', 'wcag143', 'wcag2411', 'best-practice', 'EN-9.1.4.3', 'wcag411']);

        self::assertSame(
            ['1.4.3', '2.4.11'],
            array_map(static fn(SuccessCriterion $c): string => $c->number, $criteria),
        );
    }

    public function testUnknownCriterionIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->catalog->get('9.9.9');
    }

    public function testLevelOrdering(): void
    {
        self::assertTrue(Level::A->isWithin(Level::AA));
        self::assertTrue(Level::AA->isWithin(Level::AA));
        self::assertFalse(Level::AAA->isWithin(Level::AA));
    }

    /**
     * Where the EN tags of axe-core are knowingly ignored. Each entry needs a reason.
     */
    private const array AXE_EN_TAGS_NOT_IN_EN_301_549 = [
        // WCAG 4.1.1 Parsing is obsolete in WCAG 2.2 and not in our catalog.
        '9.4.1.1',
        // 2.1.3 Keyboard (No Exception) is AAA. EN 301 549 chapter 9 only adopts levels A and
        // AA, so clause 9.2.1.3 does not exist; axe-core 4.14 tags it anyway.
        '9.2.1.3',
    ];

    /**
     * axe-core tags its rules with "EN-9.x.y.z" itself, following the WCAG 2.2 based V4.1.1.
     * Our V4.1.1 mapping must agree with every such tag in the bundled axe-core version,
     * apart from the documented exceptions above.
     */
    public function testAgreesWithEnTagsOfBundledAxeCore(): void
    {
        $axe = file_get_contents(__DIR__ . '/../../../resources/axe/axe.min.js');
        self::assertIsString($axe);
        preg_match_all('/tags:\[([^\]]*)\]/', $axe, $matches);

        $checked = 0;
        foreach ($matches[1] as $tagList) {
            preg_match_all('/"([^"]+)"/', $tagList, $tags);
            $enClauses = array_values(array_filter($tags[1], static fn(string $t): bool => str_starts_with($t, 'EN-9.')));
            if ($enClauses === []) {
                continue;
            }
            $expected = array_map(static fn(string $t): string => substr($t, 3), $enClauses);
            $ours = array_values(array_filter(
                array_map(
                    static fn(SuccessCriterion $c): ?string => $c->en301549Clause(En301549Version::V4_1_1),
                    $this->catalog->fromAxeTags($tags[1]),
                ),
                static fn(?string $clause): bool => $clause !== null,
            ));
            sort($expected);
            sort($ours);
            $expected = array_values(array_diff($expected, self::AXE_EN_TAGS_NOT_IN_EN_301_549));

            self::assertSame($expected, $ours, 'Tags: ' . $tagList);
            ++$checked;
        }

        self::assertGreaterThan(20, $checked, 'axe-core rule metadata could not be found in axe.min.js');
    }
}
