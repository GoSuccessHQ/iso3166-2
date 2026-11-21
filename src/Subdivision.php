<?php

declare(strict_types=1);

namespace GoSuccess\Iso3166;

/**
 * One subdivision of a country as defined by ISO 3166-2, with its name in one language.
 */
final readonly class Subdivision
{
    /**
     * @param string $code Full ISO 3166-2 code, for example `DE-BY`.
     * @param string $countryCode ISO 3166-1 alpha-2 code of the country, for example `DE`.
     * @param string $localCode The part after the hyphen, for example `BY`.
     * @param string $name Name in the language of the {@see Subdivisions} instance.
     * @param string $type Category as named by ISO 3166-2 (English), for example `Land` or `Metropolitan department`.
     * @param string|null $parentCode Full code of the parent subdivision, or null for a top-level subdivision.
     */
    public function __construct(
        public string $code,
        public string $countryCode,
        public string $localCode,
        public string $name,
        public string $type,
        public ?string $parentCode,
    ) {}
}
