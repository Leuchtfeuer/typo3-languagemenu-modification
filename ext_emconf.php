<?php

$EM_CONF['languagemod'] = [
    'title' => 'Language Modification',
    'description' => 'Modifies language menu entries and hreflang link tags',
    'version' => '1.1.0-dev',
    'category' => 'fe',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-10.4.99',
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
