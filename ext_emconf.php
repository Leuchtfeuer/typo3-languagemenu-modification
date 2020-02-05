<?php

$EM_CONF['languagemod'] = [
    'title' => 'Language Modification',
    'description' => 'Modifies language menu entries and hreflang link tags',
    'version' => '1.0.0',
    'category' => 'fe',
    'constraints' => [
        'depends' => [
            'typo3' => '9.5.0-9.5.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'state' => 'beta',
    'uploadfolder' => false,
    'createDirs' => '',
    'clearCacheOnLoad' => true,
    'author' => 'Florian Wessels',
    'author_email' => 'typo3-ext@bitmotion.de',
    'author_company' => 'Bitmotion GmbH',
    'autoload' => [
        'psr-4' => [
            'Bitmotion\\Languagemod\\' => 'Classes/',
        ],
    ],
];
