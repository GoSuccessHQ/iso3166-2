# ISO 3166-2 subdivisions for PHP

All country subdivisions of **ISO 3166-2** (states, provinces, regions, …) with
names in **English** and **German**, for **PHP 8.4+**, without any dependency:
no intl, no gettext, no network at runtime.

- 5,046 subdivisions in 200 countries, with type and parent (for example the
  French departments below their regions).
- Plain PHP arrays, loaded once per process and kept in shared memory by
  opcache.
- Generated from pinned, checksummed sources: Debian iso-codes and Unicode CLDR.

## Installation

```bash
composer require gosuccess/iso3166-2
```

## Usage

```php
use GoSuccess\Iso3166\Subdivisions;

$subdivisions = new Subdivisions('de_DE'); // or 'de', 'de-DE'; unknown languages fall back to English

foreach ($subdivisions->forCountry('DE') as $state) {
    echo $state->code, ' ', $state->name, PHP_EOL; // DE-BB Brandenburg, DE-BE Berlin, …
}

$bavaria = $subdivisions->get('DE-BY');
$bavaria->countryCode; // 'DE'
$bavaria->localCode;   // 'BY' – the part after the hyphen
$bavaria->type;        // 'Land' – as named by ISO 3166-2, in English
$bavaria->parentCode;  // null

(new Subdivisions('en'))->get('FR-01')->parentCode; // 'FR-ARA'
```

| Method | Returns |
| --- | --- |
| `Subdivisions::supportedLocales()` | `['de', 'en']` |
| `Subdivisions::resolveLocale('de_AT')` | the language whose names are used: `'de'` |
| `->locale()` | the language of this instance |
| `->countries()` | alpha-2 codes of all countries with subdivisions, sorted |
| `->forCountry('DE')` | the subdivisions of one country, by code; `[]` for an unknown country |
| `->all()` | all subdivisions, by code |
| `->get('DE-BY')` | one `Subdivision` or `null` |
| `->has('DE-BY')` | `bool` |

Lists are ordered by code. To sort by name, use a collator in the language of
the list, for example `(new Collator('de_DE'))->sort(...)` from ext-intl.

## Names

- **English** names are the names of Debian iso-codes, which follow
  ISO 3166-2. These are often the local names: `Bayern`, not `Bavaria`.
- **German** names are the German translations of Debian iso-codes. Where an
  entry is not translated or marked as fuzzy, the name comes from Unicode CLDR;
  where CLDR has none either, the English name is used. With iso-codes v4.20.1
  and CLDR 49: 4,856 from iso-codes, 184 from CLDR, 6 English.

## Updating the data

The sources are pinned with version and SHA-256 in `tools/sources.php`:

1. Change the version, empty the `sha256` of the files of that source.
2. Run `composer generate`; it prints the new hashes and stops.
3. Check the files against the release, paste the hashes, run
   `composer generate` again and review the diff in `data/`.

## Development

```bash
composer install
composer check   # php-cs-fixer, PHPStan (max), PHPUnit
```

## License

The code is licensed under the [MIT License](LICENSE). The data in `data/`
keeps the licenses of its sources: **LGPL-2.1-or-later** for Debian iso-codes
and the **Unicode License v3** for CLDR. See [NOTICE](NOTICE) and
[LICENSES/](LICENSES).

In short, as we read the licenses: you may use and ship the package in any
project, open or closed. The LGPL covers only the data files: if you
distribute changed data files, they stay under the LGPL. This is not legal
advice.
