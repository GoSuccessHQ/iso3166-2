<?php

declare(strict_types=1);

/*
 * Pinned sources of the generated data. To update: change a version, empty its
 * `sha256`, run `composer generate`, check the diff and paste the printed hash.
 */
return [
    'iso-codes' => [
        'version' => 'v4.20.1',
        'files' => [
            'iso_3166-2.json' => [
                'url' => 'https://salsa.debian.org/iso-codes-team/iso-codes/-/raw/{version}/data/iso_3166-2.json',
                'sha256' => '78c90ef7fc25b5c2631aac5f089bc9ff6ec22c025c05b6ddbc087a1f1be2e46a',
            ],
            'de.po' => [
                'url' => 'https://salsa.debian.org/iso-codes-team/iso-codes/-/raw/{version}/iso_3166-2/de.po',
                'sha256' => '8483b0242d080b966ae30a2acb4a983428527ca10891518bb17ff6f16152a5e7',
            ],
        ],
    ],
    'cldr' => [
        'version' => 'release-49',
        'files' => [
            'de.xml' => [
                'url' => 'https://raw.githubusercontent.com/unicode-org/cldr/{version}/common/subdivisions/de.xml',
                'sha256' => '7cb92c1ba2bcc2a8f14210c4adc4534260637aacdeeeb7a821fe5a989842b178',
            ],
            'en.xml' => [
                'url' => 'https://raw.githubusercontent.com/unicode-org/cldr/{version}/common/subdivisions/en.xml',
                'sha256' => '637705773cb923d63bf71abaf05e6246fd853ef98f143bd9796b11d2c5ac10c0',
            ],
        ],
        /*
         * Codes whose CLDR key names a different subdivision than ISO 3166-2 today, checked for
         * release-49: CLDR keeps the old numbering of Iran and the 16 former regions of Morocco,
         * and reuses `ug331` for Zombo. These names come from iso-codes instead. Review this list
         * with every new CLDR release.
         */
        'skip' => [
            'IR',
            'MA-01', 'MA-02', 'MA-03', 'MA-04', 'MA-05', 'MA-06',
            'MA-07', 'MA-08', 'MA-09', 'MA-10', 'MA-11', 'MA-12',
            'UG-331',
        ],
    ],
];
