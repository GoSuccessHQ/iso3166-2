<?php

declare(strict_types=1);

/*
 * Downloads the pinned sources (tools/sources.php), verifies their SHA-256 and
 * writes data/subdivisions.php and data/names/<locale>.php.
 *
 * Usage: php tools/generate.php (or `composer generate`). Needs no Composer
 * autoloader, so it also runs from an installed copy of the package.
 */

use GoSuccess\Iso3166\Tools\Generator;

require_once __DIR__ . '/Generator.php';

/** @var array<string, array{version: string, files: array<string, array{url: string, sha256: string}>, skip?: list<string>}> $sources */
$sources = require __DIR__ . '/sources.php';
$root = dirname(__DIR__);
$missingHash = false;

/**
 * Downloads one pinned file and checks its hash; an empty hash only prints the actual one.
 */
$fetch = static function (string $source, string $file) use ($sources, &$missingHash): string {
    $spec = $sources[$source]['files'][$file];
    $url = str_replace('{version}', rawurlencode($sources[$source]['version']), $spec['url']);
    $context = stream_context_create(['http' => ['timeout' => 60, 'user_agent' => 'gosuccess-iso3166-2-generator']]);
    $content = file_get_contents($url, false, $context);

    if ($content === false || $content === '') {
        fwrite(STDERR, "Download failed: {$url}\n");
        exit(1);
    }

    $hash = hash('sha256', $content);

    if ($spec['sha256'] === '') {
        fwrite(STDERR, "No hash pinned for {$source}/{$file}; actual: {$hash}\n");
        $missingHash = true;
    } elseif (!hash_equals($spec['sha256'], $hash)) {
        fwrite(STDERR, "Hash mismatch for {$source}/{$file}: expected {$spec['sha256']}, got {$hash}\n");
        exit(1);
    }

    return $content;
};

$cldrSkip = $sources['cldr']['skip'] ?? [];
$isoCodes = Generator::parseIsoCodes($fetch('iso-codes', 'iso_3166-2.json'));
$germanPo = $fetch('iso-codes', 'de.po');
$german = Generator::parsePo($germanPo);
$cldrGerman = Generator::withoutCodes(Generator::parseCldr($fetch('cldr', 'de.xml')), $cldrSkip);
$cldrEnglish = Generator::withoutCodes(Generator::parseCldr($fetch('cldr', 'en.xml')), $cldrSkip);

if ($missingHash) {
    fwrite(STDERR, "Pin the hashes above in tools/sources.php and run again.\n");
    exit(1);
}

$isoVersion = $sources['iso-codes']['version'];
$cldrVersion = $sources['cldr']['version'];
$date = gmdate('Y-m-d');
$isoNotice = "Data from Debian iso-codes {$isoVersion} (https://salsa.debian.org/iso-codes-team/iso-codes),\n"
    . "licensed under LGPL-2.1-or-later (see LICENSES/LGPL-2.1-or-later.txt).\n"
    . 'SPDX-FileCopyrightText of data/iso_3166-2.json: 2016 Dr. Tobias Quathamer <toddy@debian.org>';
$cldrNotice = static fn(string $file): string => "Unicode CLDR {$cldrVersion} common/subdivisions/{$file} (https://github.com/unicode-org/cldr),\n"
    . 'licensed under Unicode-3.0 (see LICENSES/Unicode-3.0.txt), Copyright © 2001–2026 Unicode, Inc.';
$modified = "Modified by GoSuccess GmbH on {$date}: converted to PHP arrays by tools/generate.php from the\n"
    . 'pinned sources in tools/sources.php. Do not edit; changes are lost on the next run.';
$germanCopyrights = implode("\n", array_map(
    static fn(string $line): string => "SPDX-FileCopyrightText of iso_3166-2/de.po: {$line}",
    Generator::parsePoCopyrights($germanPo),
));

$englishNames = Generator::names($isoCodes, [], $cldrEnglish);
$germanNames = Generator::names($isoCodes, $german, $cldrGerman);

$files = [
    'data/subdivisions.php' => Generator::export(
        Generator::index($isoCodes),
        "ISO 3166-2 subdivisions: code => [type, parent code], grouped by country.\n"
        . "Codes, types and parents of data/iso_3166-2.json; names are in data/names/.\n"
        . "{$isoNotice}\n{$modified}",
    ),
    'data/names/en.php' => Generator::export(
        $englishNames['names'],
        "ISO 3166-2 subdivision names in English: names from\n"
        . $cldrNotice('en.xml') . ";\n"
        . "where CLDR has none (or its key names another subdivision), the name of data/iso_3166-2.json.\n"
        . "{$isoNotice}\n{$modified}",
    ),
    'data/names/de.php' => Generator::export(
        $germanNames['names'],
        "ISO 3166-2 subdivision names in German: translations of iso_3166-2/de.po; where missing or fuzzy,\n"
        . 'names from ' . $cldrNotice('de.xml') . ";\n"
        . "else the name of data/iso_3166-2.json.\n"
        . "{$isoNotice}\n{$germanCopyrights}\n{$modified}",
    ),
];

/*
 * Keeps the date of the last change: a file whose content differs only in the date of
 * "Modified by … on" is left as it is.
 */
$withoutDate = static fn(string $content): string => (string) preg_replace(
    '/^(\/\/ Modified by GoSuccess GmbH on )\d{4}-\d{2}-\d{2}:/m',
    '$1DATE:',
    $content,
);

foreach ($files as $path => $content) {
    $current = @file_get_contents($root . '/' . $path);

    if ($current !== false && $withoutDate($current) === $withoutDate($content)) {
        continue;
    }

    file_put_contents($root . '/' . $path, $content);
    echo "Wrote {$path}\n";
}

printf(
    "%d subdivisions in %d countries.\nEnglish: %d from CLDR, %d from iso-codes.\nGerman: %d from iso-codes, %d from CLDR, %d ISO names.\n",
    count($isoCodes),
    count(Generator::index($isoCodes)),
    $englishNames['sources']['cldr'],
    $englishNames['sources']['iso'],
    $germanNames['sources']['translation'],
    $germanNames['sources']['cldr'],
    $germanNames['sources']['iso'],
);
