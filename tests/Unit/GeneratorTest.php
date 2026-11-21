<?php

declare(strict_types=1);

namespace GoSuccess\Iso3166\Tests\Unit;

use GoSuccess\Iso3166\Tools\Generator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GeneratorTest extends TestCase
{
    #[Test]
    public function itReadsCodesTypesAndParentsSortedByCode(): void
    {
        $codes = Generator::parseIsoCodes($this->fixture('iso_3166-2.json'));

        self::assertSame(['AG-03', 'BB-03', 'CY-06', 'DE-BY', 'DZ-53', 'FR-01', 'FR-ARA'], array_keys($codes));
        self::assertSame(['name' => 'Ain', 'type' => 'Metropolitan department', 'parent' => 'FR-ARA'], $codes['FR-01']);
        self::assertNull($codes['DE-BY']['parent']);
    }

    #[Test]
    public function itRejectsAnUnknownParent(): void
    {
        $this->expectException(RuntimeException::class);

        Generator::parseIsoCodes('{"3166-2": [{"code": "FR-01", "name": "Ain", "parent": "XXX", "type": "Department"}]}');
    }

    #[Test]
    public function itRejectsAnInvalidCode(): void
    {
        $this->expectException(RuntimeException::class);

        Generator::parseIsoCodes('{"3166-2": [{"code": "de-by", "name": "Bayern", "type": "Land"}]}');
    }

    #[Test]
    public function itReadsTranslationsForEveryCodeOfAnEntryAndSkipsFuzzyAndEmptyOnes(): void
    {
        $translations = Generator::parsePo($this->fixture('de.po'));

        self::assertSame([
            'AG-03' => 'Saint George',
            'BB-03' => 'Saint George',
            'DE-BY' => 'Bayern "Freistaat"',
        ], $translations);
    }

    #[Test]
    public function itReadsCldrNamesWithoutAlternatives(): void
    {
        self::assertSame(
            ['cy06' => 'Bezirk Kyrenia', 'deby' => 'Bayern (CLDR)'],
            Generator::parseCldr($this->fixture('de.xml')),
        );
    }

    #[Test]
    public function itPrefersTheTranslationThenCldrThenTheIsoName(): void
    {
        $codes = Generator::parseIsoCodes($this->fixture('iso_3166-2.json'));
        $result = Generator::names(
            $codes,
            Generator::parsePo($this->fixture('de.po')),
            Generator::parseCldr($this->fixture('de.xml')),
        );

        self::assertSame('Bayern "Freistaat"', $result['names']['DE-BY']);
        self::assertSame('Bezirk Kyrenia', $result['names']['CY-06']);
        self::assertSame('In Salah', $result['names']['DZ-53']);
        self::assertSame(['iso' => 3, 'translation' => 3, 'cldr' => 1], $result['sources']);
    }

    #[Test]
    public function itExportsLoadablePhp(): void
    {
        $codes = Generator::parseIsoCodes($this->fixture('iso_3166-2.json'));
        $index = Generator::index($codes);
        $file = tempnam(sys_get_temp_dir(), 'iso');
        self::assertIsString($file);

        try {
            file_put_contents($file, Generator::export($index, "Header\nsecond line"));
            $loaded = require $file;
        } finally {
            unlink($file);
        }

        self::assertSame($index, $loaded);
        self::assertSame(['FR-01' => ['Metropolitan department', 'FR-ARA'], 'FR-ARA' => ['Metropolitan region', null]], $index['FR']);
    }

    private function fixture(string $name): string
    {
        $content = file_get_contents(\dirname(__DIR__) . '/Fixtures/' . $name);
        self::assertIsString($content);

        return $content;
    }
}
