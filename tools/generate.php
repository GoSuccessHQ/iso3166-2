<?php

declare(strict_types=1);

/*
 * Downloads the pinned sources (tools/sources.php), verifies their SHA-256 and
 * writes data/subdivisions.php and data/names/<locale>.php.
 *
 * Usage: php tools/generate.php
 */

use GoSuccess\Iso3166\Tools\Generator;

require dirname(__DIR__) . '/vendor/autoload.php';

/** @var array<string, array{version: string, files: array<string, array{url: string, sha256: string}>}> $sources */
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

$isoCodes = Generator::parseIsoCodes($fetch('iso-codes', 'iso_3166-2.json'));
$german = Generator::parsePo($fetch('iso-codes', 'de.po'));
$cldrGerman = Generator::parseCldr($fetch('cldr', 'de.xml'));

if ($missingHash) {
    fwrite(STDERR, "Pin the hashes above in tools/sources.php and run again.\n");
    exit(1);
}

$isoVersion = $sources['iso-codes']['version'];
$cldrVersion = $sources['cldr']['version'];
$isoNotice = "Data from Debian iso-codes {$isoVersion} (https://salsa.debian.org/iso-codes-team/iso-codes),\n"
    . 'licensed under LGPL-2.1-or-later (see LICENSES/LGPL-2.1-or-later.txt).';
$generated = "Modified by GoSuccess GmbH: converted to PHP arrays by tools/generate.php; the dates of changes\n"
    . 'are in the Git history of this file. Do not edit; changes are lost on the next run.';

$files = [
    'data/subdivisions.php' => Generator::export(
        Generator::index($isoCodes),
        "ISO 3166-2 subdivisions: code => [type, parent code], grouped by country.\n"
        . "Converted from data/iso_3166-2.json to PHP arrays; names are in data/names/.\n"
        . "{$isoNotice}\n{$generated}",
    ),
    'data/names/en.php' => Generator::export(
        Generator::names($isoCodes, [], [])['names'],
        "ISO 3166-2 subdivision names in English: the names of data/iso_3166-2.json.\n"
        . "{$isoNotice}\n{$generated}",
    ),
];

$germanNames = Generator::names($isoCodes, $german, $cldrGerman);
$files['data/names/de.php'] = Generator::export(
    $germanNames['names'],
    "ISO 3166-2 subdivision names in German: translations of iso_3166-2/de.po; where missing or fuzzy,\n"
    . "names from Unicode CLDR {$cldrVersion} common/subdivisions/de.xml (https://github.com/unicode-org/cldr),\n"
    . "licensed under Unicode-3.0 (see LICENSES/Unicode-3.0.txt); else the English name.\n"
    . "{$isoNotice}\n{$generated}",
);

foreach ($files as $path => $content) {
    file_put_contents($root . '/' . $path, $content);
}

printf(
    "%d subdivisions in %d countries. German: %d from iso-codes, %d from CLDR, %d ISO names.\n",
    count($isoCodes),
    count(Generator::index($isoCodes)),
    $germanNames['sources']['translation'],
    $germanNames['sources']['cldr'],
    $germanNames['sources']['iso'],
);
