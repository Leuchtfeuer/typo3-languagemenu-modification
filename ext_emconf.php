<?php

########################################################################
# Extension Manager/Repository config file for ext "bm_site".
#
# Auto generated 16-07-2012 12:14
#
# Manual updates:
# Only the data in the array - everything else is removed by next
# writing. "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = [
    'title' => 'Language modification',
    'description' => 'Modifies language menu entries and hreflang link tags',
    'version' => '1.0.0',
    'category' => 'fe',
    'constraints' => [
        'depends' => [
            'typo3' => '8.7.0-9.5.99',
            'php' => '7.0.0-7.3.99'
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'state' => 'alpha',
    'uploadfolder' => false,
    'createDirs' => '',
    'clearCacheOnLoad' => true,
    'author' => 'Florian Wessels',
    'author_email' => 'typo3-ext@bitmotion.de',
    'author_company' => 'Bitmotion GmbH',
    'autoload' => [
        'psr-4' => [
            'Bitmotion\\Languagemod\\' => 'Classes/'
        ],
    ],
];
