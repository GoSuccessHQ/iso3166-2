<?php

declare(strict_types=1);

namespace GoSuccess\Iso3166;

/**
 * Looks up ISO 3166-2 subdivisions with names in one language.
 *
 * The data ships as plain PHP arrays in `data/` and is loaded once per process,
 * so opcache keeps it in shared memory. Subdivisions are returned in the order
 * of their codes; sort by name yourself if needed (for example with `Collator`).
 */
final class Subdivisions
{
    /** Language used when a requested locale is not available. */
    public const string FALLBACK_LOCALE = 'en';

    /** @var array<string, array<string, array{0: string, 1: string|null}>>|null */
    private static ?array $index = null;

    /** @var array<string, array<string, string>> */
    private static array $names = [];

    /** @var list<string>|null */
    private static ?array $locales = null;

    private readonly string $locale;

    /**
     * @param string $locale A language (`de`), or a locale like `de_DE` or `de-DE`;
     *                       only the language counts. Unknown languages fall back to English.
     */
    public function __construct(string $locale = self::FALLBACK_LOCALE)
    {
        $this->locale = self::resolveLocale($locale);
    }

    /**
     * Languages with names in this package.
     *
     * @return list<string>
     */
    public static function supportedLocales(): array
    {
        if (self::$locales === null) {
            $locales = [];

            foreach (glob(self::dataDirectory() . '/names/*.php') ?: [] as $file) {
                $locales[] = basename($file, '.php');
            }

            sort($locales);
            self::$locales = $locales;
        }

        return self::$locales;
    }

    /**
     * Maps a locale to the language whose names are used: `de_DE`, `de-AT` and `DE` become `de`.
     */
    public static function resolveLocale(string $locale): string
    {
        $language = strtolower((string) preg_replace('/[-_].*$/', '', trim($locale)));

        return \in_array($language, self::supportedLocales(), true) ? $language : self::FALLBACK_LOCALE;
    }

    /**
     * The language whose names this instance returns.
     */
    public function locale(): string
    {
        return $this->locale;
    }

    /**
     * ISO 3166-1 alpha-2 codes of all countries that have subdivisions, sorted.
     *
     * @return list<string>
     */
    public function countries(): array
    {
        return array_keys(self::index());
    }

    /**
     * All subdivisions of one country, ordered by code; empty for an unknown country
     * or a country without subdivisions.
     *
     * @param string $countryCode ISO 3166-1 alpha-2 code, case-insensitive.
     * @return list<Subdivision>
     */
    public function forCountry(string $countryCode): array
    {
        $country = strtoupper(trim($countryCode));
        $result = [];

        foreach (self::index()[$country] ?? [] as $code => $entry) {
            $result[] = $this->make($country, $code, $entry);
        }

        return $result;
    }

    /**
     * All subdivisions of all countries, ordered by code.
     *
     * @return list<Subdivision>
     */
    public function all(): array
    {
        $result = [];

        foreach (self::index() as $country => $entries) {
            foreach ($entries as $code => $entry) {
                $result[] = $this->make($country, $code, $entry);
            }
        }

        return $result;
    }

    /**
     * One subdivision by its full code (`DE-BY`, case-insensitive), or null if there is none.
     */
    public function get(string $code): ?Subdivision
    {
        $code = strtoupper(trim($code));
        $country = substr($code, 0, 2);
        $entry = self::index()[$country][$code] ?? null;

        return $entry === null ? null : $this->make($country, $code, $entry);
    }

    /**
     * Whether a full code (`DE-BY`, case-insensitive) is a known subdivision.
     */
    public function has(string $code): bool
    {
        $code = strtoupper(trim($code));

        return isset(self::index()[substr($code, 0, 2)][$code]);
    }

    /**
     * Builds the value object for one entry of the index.
     *
     * @param array{0: string, 1: string|null} $entry
     */
    private function make(string $country, string $code, array $entry): Subdivision
    {
        return new Subdivision(
            code: $code,
            countryCode: $country,
            localCode: substr($code, 3),
            name: $this->names()[$code] ?? $code,
            type: $entry[0],
            parentCode: $entry[1],
        );
    }

    /**
     * Names in the language of this instance, keyed by full code.
     *
     * @return array<string, string>
     */
    private function names(): array
    {
        if (!isset(self::$names[$this->locale])) {
            /** @var array<string, string> $names */
            $names = require self::dataDirectory() . '/names/' . $this->locale . '.php';
            self::$names[$this->locale] = $names;
        }

        return self::$names[$this->locale];
    }

    /**
     * Type and parent of every subdivision, grouped by country.
     *
     * @return array<string, array<string, array{0: string, 1: string|null}>>
     */
    private static function index(): array
    {
        if (self::$index === null) {
            /** @var array<string, array<string, array{0: string, 1: string|null}>> $index */
            $index = require self::dataDirectory() . '/subdivisions.php';
            self::$index = $index;
        }

        return self::$index;
    }

    /**
     * Directory with the generated data files.
     */
    private static function dataDirectory(): string
    {
        return \dirname(__DIR__) . '/data';
    }
}
