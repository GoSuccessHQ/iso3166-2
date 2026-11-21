<?php

declare(strict_types=1);

namespace GoSuccess\Iso3166\Tools;

use RuntimeException;

/**
 * Turns the source files (Debian iso-codes, Unicode CLDR) into the PHP data files in `data/`.
 *
 * Kept free of I/O apart from {@see self::export()} so the parsing can be tested with fixtures.
 */
final class Generator
{
    /**
     * Reads `iso_3166-2.json` of Debian iso-codes.
     *
     * @return array<string, array{name: string, type: string, parent: string|null}> Keyed by full code, sorted.
     */
    public static function parseIsoCodes(string $json): array
    {
        $data = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);

        if (!\is_array($data) || !\is_array($data['3166-2'] ?? null)) {
            throw new RuntimeException('iso_3166-2.json has no "3166-2" list.');
        }

        $result = [];

        foreach ($data['3166-2'] as $entry) {
            if (!\is_array($entry)) {
                throw new RuntimeException('Unexpected entry in iso_3166-2.json.');
            }

            $code = $entry['code'] ?? null;
            $name = $entry['name'] ?? null;
            $type = $entry['type'] ?? null;
            $parent = $entry['parent'] ?? null;

            if (!\is_string($code) || preg_match('/\A[A-Z]{2}-[A-Z0-9]{1,3}\z/', $code) !== 1) {
                throw new RuntimeException('Invalid code in iso_3166-2.json: ' . var_export($code, true));
            }

            if (!\is_string($name) || $name === '' || !\is_string($type) || $type === '') {
                throw new RuntimeException("Missing name or type for {$code}.");
            }

            if ($parent !== null) {
                // Debian stores parents either as full code or as the part after the hyphen.
                if (!\is_string($parent)) {
                    throw new RuntimeException("Invalid parent for {$code}.");
                }

                $parent = str_contains($parent, '-') ? $parent : substr($code, 0, 3) . $parent;
            }

            $result[$code] = ['name' => $name, 'type' => $type, 'parent' => $parent];
        }

        foreach ($result as $code => $entry) {
            if ($entry['parent'] !== null && !isset($result[$entry['parent']])) {
                throw new RuntimeException("Unknown parent {$entry['parent']} of {$code}.");
            }
        }

        ksort($result, \SORT_STRING);

        return $result;
    }

    /**
     * Reads a gettext catalog of Debian iso-codes for ISO 3166-2.
     *
     * Entries are matched by their `#. Name for XX-YY` comments, as one name may serve several codes.
     * Fuzzy, obsolete and empty translations are skipped, like gettext does.
     *
     * @return array<string, string> Translation keyed by full code.
     */
    public static function parsePo(string $po): array
    {
        $result = [];

        foreach (preg_split('/\R\R+/', str_replace("\r\n", "\n", $po)) ?: [] as $block) {
            $codes = [];
            $fuzzy = false;
            $field = null;
            $values = ['msgid' => '', 'msgstr' => ''];

            foreach (explode("\n", $block) as $line) {
                if (str_starts_with($line, '#.')) {
                    preg_match_all('/Name for ([A-Z]{2}-[A-Z0-9]{1,3})\b/', $line, $matches);
                    array_push($codes, ...$matches[1]);
                } elseif (str_starts_with($line, '#,')) {
                    $fuzzy = $fuzzy || str_contains($line, 'fuzzy');
                } elseif (preg_match('/\A(msgid|msgstr) "(.*)"\z/', $line, $m) === 1) {
                    $field = $m[1];
                    $values[$field] = self::unquote($m[2]);
                } elseif ($field !== null && preg_match('/\A"(.*)"\z/', $line, $m) === 1) {
                    $values[$field] .= self::unquote($m[1]);
                }
            }

            $translation = trim($values['msgstr']);

            if ($codes === [] || $fuzzy || $translation === '') {
                continue;
            }

            foreach ($codes as $code) {
                $result[$code] = $translation;
            }
        }

        return $result;
    }

    /**
     * Reads `common/subdivisions/<lang>.xml` of Unicode CLDR.
     *
     * CLDR keys are lower case without hyphen (`deby` for `DE-BY`).
     *
     * @return array<string, string> Name keyed by CLDR key.
     */
    public static function parseCldr(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $document = simplexml_load_string($xml, options: \LIBXML_NONET);

            if ($document === false) {
                throw new RuntimeException('Invalid CLDR XML.');
            }

            $result = [];

            foreach ($document->xpath('//subdivisions/subdivision') ?: [] as $node) {
                $key = (string) $node['type'];
                $name = trim((string) $node);

                if ($key !== '' && $name !== '' && (string) $node['alt'] === '') {
                    $result[$key] = $name;
                }
            }

            return $result;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * Drops CLDR names whose keys do not match the current ISO 3166-2 codes.
     *
     * CLDR keeps some codes that ISO has renumbered or reused (Iran, the regions of Morocco), so
     * the same key names a different subdivision there. An entry is a full code (`MA-01`) or a
     * country (`IR`) for all its codes.
     *
     * @param array<string, string> $cldr From {@see self::parseCldr()}.
     * @param list<string> $codes
     * @return array<string, string>
     */
    public static function withoutCodes(array $cldr, array $codes): array
    {
        foreach ($codes as $code) {
            if (preg_match('/\A[A-Z]{2}(-[A-Z0-9]{1,3})?\z/', $code) !== 1) {
                throw new RuntimeException("Invalid code to skip in CLDR: {$code}");
            }

            $key = strtolower(str_replace('-', '', $code));

            if (\strlen($code) === 2) {
                $cldr = array_filter($cldr, static fn(string $cldrKey): bool => !str_starts_with($cldrKey, $key), \ARRAY_FILTER_USE_KEY);
            } else {
                unset($cldr[$key]);
            }
        }

        return $cldr;
    }

    /**
     * Reads the copyright lines of a gettext catalog of Debian iso-codes, to keep them with the data.
     *
     * @return list<string> The `SPDX-FileCopyrightText` values, in file order.
     */
    public static function parsePoCopyrights(string $po): array
    {
        preg_match_all('/^# SPDX-FileCopyrightText: (.+)$/m', str_replace("\r\n", "\n", $po), $matches);

        return array_map(trim(...), $matches[1]);
    }

    /**
     * Picks the name of every subdivision in one language: the translation of Debian iso-codes,
     * else CLDR, else the ISO name. For English, pass no translations: CLDR first, then the ISO name.
     *
     * @param array<string, array{name: string, type: string, parent: string|null}> $isoCodes
     * @param array<string, string> $translations From {@see self::parsePo()}; empty for English.
     * @param array<string, string> $cldr From {@see self::parseCldr()}; empty to skip.
     * @return array{names: array<string, string>, sources: array{iso: int, translation: int, cldr: int}}
     */
    public static function names(array $isoCodes, array $translations, array $cldr): array
    {
        $names = [];
        $sources = ['iso' => 0, 'translation' => 0, 'cldr' => 0];

        foreach ($isoCodes as $code => $entry) {
            $cldrKey = strtolower(str_replace('-', '', $code));

            if (isset($translations[$code])) {
                $names[$code] = $translations[$code];
                $sources['translation']++;
            } elseif (isset($cldr[$cldrKey])) {
                $names[$code] = $cldr[$cldrKey];
                $sources['cldr']++;
            } else {
                $names[$code] = $entry['name'];
                $sources['iso']++;
            }
        }

        return ['names' => $names, 'sources' => $sources];
    }

    /**
     * Groups type and parent by country, the shape of `data/subdivisions.php`.
     *
     * @param array<string, array{name: string, type: string, parent: string|null}> $isoCodes
     * @return array<string, array<string, array{0: string, 1: string|null}>>
     */
    public static function index(array $isoCodes): array
    {
        $index = [];

        foreach ($isoCodes as $code => $entry) {
            $index[substr($code, 0, 2)][$code] = [$entry['type'], $entry['parent']];
        }

        ksort($index, \SORT_STRING);

        return $index;
    }

    /**
     * Renders a data file with a header that names its sources and license.
     *
     * @param array<array-key, mixed> $data
     */
    public static function export(array $data, string $header): string
    {
        $lines = ['<?php', '', 'declare(strict_types=1);', ''];

        foreach (explode("\n", trim($header)) as $line) {
            $lines[] = rtrim('// ' . $line);
        }

        $lines[] = '';
        $lines[] = 'return ' . self::exportValue($data, 0) . ';';

        return implode("\n", $lines) . "\n";
    }

    /**
     * Short-array export with one entry per line, stable and readable in diffs.
     */
    private static function exportValue(mixed $value, int $depth): string
    {
        if ($value === null) {
            return 'null';
        }

        if (!\is_array($value)) {
            return var_export($value, true);
        }

        if ($value === []) {
            return '[]';
        }

        if (array_is_list($value) && array_filter($value, \is_array(...)) === []) {
            return '[' . implode(', ', array_map(fn(mixed $item): string => self::exportValue($item, $depth + 1), $value)) . ']';
        }

        $indent = str_repeat('    ', $depth + 1);
        $lines = [];

        foreach ($value as $key => $item) {
            $lines[] = $indent . var_export($key, true) . ' => ' . self::exportValue($item, $depth + 1) . ',';
        }

        return "[\n" . implode("\n", $lines) . "\n" . str_repeat('    ', $depth) . ']';
    }

    /**
     * Resolves the escapes of a gettext string.
     */
    private static function unquote(string $value): string
    {
        return stripcslashes($value);
    }
}
