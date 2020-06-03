<?php

$EM_CONF['languagemod'] = [
    'title' => 'Language Modification',
    'description' => 'Modifies language menu entries and hreflang link tags',
    'version' => '1.0.1',
    'category' => 'fe',
    'constraints' => [
        'depends' => [
            'typo3' => '9.5.0-9.5.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'state' => 'stable',
    'uploadfolder' => false,
    'createDirs' => '',
    'clearCacheOnLoad' => true,
    'author' => 'Florian Wessels',
    'author_email' => 'f.wessels@Leuchtfeuer.com',
    'author_company' => 'Leuchtfeuer Digital Marketing',
    'autoload' => [
        'psr-4' => [
            'Bitmotion\\Languagemod\\' => 'Classes/',
        ],
    ],
];
