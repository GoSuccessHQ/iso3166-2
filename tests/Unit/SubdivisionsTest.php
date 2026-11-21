<?php

declare(strict_types=1);

namespace GoSuccess\Iso3166\Tests\Unit;

use GoSuccess\Iso3166\Subdivision;
use GoSuccess\Iso3166\Subdivisions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SubdivisionsTest extends TestCase
{
    #[Test]
    public function itShipsEnglishAndGerman(): void
    {
        self::assertSame(['de', 'en'], Subdivisions::supportedLocales());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function locales(): iterable
    {
        yield 'language' => ['de', 'de'];
        yield 'POSIX locale' => ['de_DE', 'de'];
        yield 'BCP 47 tag' => ['de-AT', 'de'];
        yield 'upper case' => ['DE', 'de'];
        yield 'English' => ['en_US', 'en'];
        yield 'unknown language' => ['fr_FR', 'en'];
        yield 'empty' => ['', 'en'];
    }

    #[Test]
    #[DataProvider('locales')]
    public function itResolvesTheLanguageOfALocale(string $locale, string $expected): void
    {
        self::assertSame($expected, new Subdivisions($locale)->locale());
    }

    #[Test]
    public function itListsTheGermanStatesInGerman(): void
    {
        $states = new Subdivisions('de_DE')->forCountry('de');

        self::assertCount(16, $states);
        self::assertSame('DE-BB', $states[0]->code);

        $bavaria = array_values(array_filter($states, static fn(Subdivision $s): bool => $s->code === 'DE-BY'))[0];
        self::assertEquals(new Subdivision('DE-BY', 'DE', 'BY', 'Bayern', 'Land', null), $bavaria);
    }

    #[Test]
    public function itNamesSubdivisionsPerLanguage(): void
    {
        self::assertSame('Kalifornien', new Subdivisions('de')->get('US-CA')?->name);
        self::assertSame('California', new Subdivisions('en')->get('us-ca')?->name);
    }

    #[Test]
    public function itCarriesTheParent(): void
    {
        $ain = new Subdivisions()->get('FR-01');

        self::assertNotNull($ain);
        self::assertSame('FR-ARA', $ain->parentCode);
        self::assertSame('01', $ain->localCode);
        self::assertTrue(new Subdivisions()->has($ain->parentCode));
    }

    #[Test]
    public function itReturnsNothingForUnknownCodes(): void
    {
        $subdivisions = new Subdivisions();

        self::assertSame([], $subdivisions->forCountry('XX'));
        self::assertSame([], $subdivisions->forCountry(''));
        self::assertNull($subdivisions->get('DE-XX'));
        self::assertNull($subdivisions->get(''));
        self::assertFalse($subdivisions->has('DE'));
    }

    #[Test]
    public function everySubdivisionHasANameInEveryLanguageAndAKnownParent(): void
    {
        $english = new Subdivisions('en')->all();

        self::assertGreaterThan(5000, \count($english));

        foreach (Subdivisions::supportedLocales() as $locale) {
            $subdivisions = new Subdivisions($locale);

            foreach ($subdivisions->all() as $subdivision) {
                self::assertNotSame($subdivision->code, $subdivision->name, "{$locale}: {$subdivision->code}");
                self::assertSame($subdivision->countryCode . '-' . $subdivision->localCode, $subdivision->code);
                self::assertTrue($subdivision->parentCode === null || $subdivisions->has($subdivision->parentCode));
            }
        }
    }

    #[Test]
    public function countriesAreSortedAndHaveSubdivisions(): void
    {
        $subdivisions = new Subdivisions();
        $countries = $subdivisions->countries();
        $sorted = $countries;
        sort($sorted);

        self::assertSame($sorted, $countries);
        self::assertContains('DE', $countries);

        foreach ($countries as $country) {
            self::assertNotSame([], $subdivisions->forCountry($country));
        }
    }
}
