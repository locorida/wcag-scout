<?php

declare(strict_types=1);

namespace WcagScout\Tests\Unit\Report\Json;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WcagScout\Report\Json\RunDeserializer;
use WcagScout\Report\Json\RunSerializer;
use WcagScout\Tests\Support\Path;
use WcagScout\Tests\Support\RunFactory;

final class RunSerializerTest extends TestCase
{
    public function testRoundTripIsLossless(): void
    {
        $serializer = new RunSerializer();
        $json = $serializer->serialize(RunFactory::sample());

        $again = $serializer->serialize(new RunDeserializer()->deserialize($json));

        self::assertSame($json, $again);
    }

    public function testDocumentContainsDisclaimerAndBothEnClauses(): void
    {
        $data = json_decode(new RunSerializer()->serialize(RunFactory::sample()), true);

        self::assertSame(1, Path::json($data, 'schemaVersion'));
        self::assertStringContainsString('not proof of conformance', self::string(Path::json($data, 'disclaimer.en.text')));
        self::assertStringContainsString('kein Nachweis der Konformität', self::string(Path::json($data, 'disclaimer.de.text')));
        self::assertSame(['critical' => 3, 'serious' => 2, 'moderate' => 1, 'minor' => 0], Path::json($data, 'summary.bySeverity'));
        self::assertSame(
            ['number' => '1.1.1', 'level' => 'A', 'en301549' => ['3.2.1' => '9.1.1.1', '4.1.1' => '9.1.1.1']],
            Path::json($data, 'pages.0.findings.0.criteria.0'),
        );
        self::assertTrue(Path::json($data, 'pages.0.findings.3.bestPractice'));
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidDocuments(): iterable
    {
        yield 'not JSON' => ['{', 'Not valid JSON'];
        yield 'newer schema' => ['{"schemaVersion": 99}', 'Unsupported report schema version 99'];
        yield 'missing field' => ['{"schemaVersion": 1}', 'report.rules is missing'];
    }

    #[DataProvider('invalidDocuments')]
    public function testRejectsInvalidDocumentsWithAPath(string $json, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        new RunDeserializer()->deserialize($json);
    }

    public function testNamesTheBrokenField(): void
    {
        $json = new RunSerializer()->serialize(RunFactory::sample());
        $broken = preg_replace('/"severity": "serious"/', '"severity": 42', $json, 1);
        self::assertIsString($broken);

        $this->expectExceptionMessage('report.pages[0].findings[2].severity must be a string.');
        new RunDeserializer()->deserialize($broken);
    }

    private static function string(mixed $value): string
    {
        self::assertIsString($value);

        return $value;
    }
}
