<?php
declare(strict_types = 1);
namespace Bitmotion\Languagemod\Hooks;

/***
 *
 * This file is part of the "TYPO3 Language Menu Modification" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2019 Florian Wessels <f.wessels@bitmotion.de>, Bitmotion GmbH
 *
 ***/

interface PageOverlayRecordIsTranslatedInterface
{
    public function isTranslatedRecord(array $record, int $languageUid, PageOverlayHook $pageOverlayHook): bool;
}
