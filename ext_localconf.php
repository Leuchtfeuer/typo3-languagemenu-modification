<?php
defined('TYPO3_MODE') || die('Access denied.');

call_user_func(
    function ($extensionKey) {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_page.php']['getPageOverlay'][$extensionKey] = \Bitmotion\Languagemod\Hooks\PageOverlayHook::class;
    }, 'languagemod'
);
