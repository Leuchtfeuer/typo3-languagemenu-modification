<?php

declare(strict_types=1);

/*
 * This file is part of the "Language Modification" extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * Florian Wessels <f.wessels@Leuchtfeuer.com>, Leuchtfeuer Digital Marketing
 */

namespace Bitmotion\Languagemod\Hooks;

interface PageOverlayRecordIsTranslatedInterface
{
    public function isTranslatedRecord(array $record, int $languageUid, PageOverlayHook $pageOverlayHook): bool;
}
