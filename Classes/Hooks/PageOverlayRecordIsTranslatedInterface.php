<?php
declare(strict_types = 1);
namespace Bitmotion\Languagemod\Hooks;

interface PageOverlayRecordIsTranslatedInterface
{
    public function isTranslatedRecord(array $record, int $languageUid, PageOverlayHook $pageOverlayHook): bool;
}
