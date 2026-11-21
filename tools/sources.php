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
        ],
    ],
];
