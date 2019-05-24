<?php
declare(strict_types=1);
namespace Bitmotion\Languagemod\Hooks;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\VersionNumberUtility;
use TYPO3\CMS\Extbase\Configuration\FrontendConfigurationManager;
use TYPO3\CMS\Frontend\Page\PageRepository;
use TYPO3\CMS\Frontend\Page\PageRepositoryGetPageOverlayHookInterface;

class PageOverlayHook implements PageRepositoryGetPageOverlayHookInterface, SingletonInterface
{
    protected $setup = [];

    protected $initialized = false;

    protected $configurationManager;

    protected $value;

    protected $tableName;

    protected $params;

    protected $queryParams;

    protected $languages;

    protected $pages;

    protected $translationChecked = false;

    public function getPageOverlay_preProcess(&$pageInput, &$lUid, PageRepository $parent)
    {
        $this->initialize();

        if (!empty($this->setup) && isset($this->setup['config.']['tx_languagemod.']) && !empty($this->setup['config.']['tx_languagemod.'])) {
            $config = $this->setup['config.']['tx_languagemod.'];
            $languages = $this->languages ?? $this->getLanguages($config);

            // Skip when we are in current language and we already checked whether given record is a translation
            if ($lUid === $GLOBALS['TSFE']->sys_language_uid && $this->translationChecked === true) {
                return;
            }

            if (in_array($lUid, $languages)) {
                $pages = $this->pages ?? $this->getPages($config);

                if (empty($pages) || in_array($GLOBALS['TSFE']->id, $pages)) {
                    $queryParams = $this->queryParams ?? $this->getQueryParams();
                    $params = $this->params ?? $this->getParameters($config);

                    if ($this->requestHasParam($queryParams, $params) && !$this->translationExist($lUid)) {
                        $lUid = 0;
                    }
                }
            }
        }
    }

    protected function initialize()
    {
        if ($this->initialized === false) {
            $configurationManager = $this->configurationManager ?? GeneralUtility::makeInstance(FrontendConfigurationManager::class);
            $setup = $configurationManager->getTypoScriptSetup();

            if (!empty($setup)) {
                $this->setup = $setup;
                $this->initialized = true;
            }
        }
    }

    protected function getLanguages(array $config): array
    {
        $this->languages = GeneralUtility::trimExplode(',', $config['languages']);

        return $this->languages;
    }

    protected function getPages(array $config): array
    {
        $this->pages = GeneralUtility::trimExplode(',', $config['pages']);

        return $this->pages;
    }

    protected function getParameters(array $config): array
    {
        $parameters = [];

        foreach ($config['params.'] as $tableName => $param) {
            $getVars = explode('.', $param);
            $parameters[] = [
                'tableName' => $tableName,
                'getVars' => array_combine($getVars, $getVars),
            ];
        }

        $this->params = $parameters;

        return $parameters;
    }

    protected function getQueryParams(): array
    {
        if ($GLOBALS['TYPO3_REQUEST'] !== null) {
            $this->queryParams = $GLOBALS['TYPO3_REQUEST']->getQueryParams();
        } else {
            // TYPO3 8
            $this->queryParams = GeneralUtility::_GET();
        }

        return $this->queryParams;
    }

    protected function requestHasParam(array $queryParameters, array $parameters): bool
    {
        foreach ($parameters as $key => $parameter) {
            if (isset($parameter['getVars']) && !empty($parameter['getVars'])) {
                $getVars = $parameter['getVars'];
                $value = 0;
                $paramsToIterate = $queryParameters;

                foreach ($getVars as $getVar) {
                    if (!isset($paramsToIterate[$getVar])) {
                        continue 2;
                    }

                    if (is_array($paramsToIterate[$getVar])) {
                        $paramsToIterate = $paramsToIterate[$getVar];
                    } else {
                        $value = (int)$paramsToIterate[$getVar];
                    }
                }

                $this->value = $value;
                $this->tableName = $parameter['tableName'];

                return true;
            }
        }

        return false;
    }

    protected function translationExist(int $languageUid): bool
    {
        if (!$this->isTranslatedRecord($languageUid)) {
            return true;
        }

        $transOrigPointerField = $GLOBALS['TCA'][$this->tableName]['ctrl']['transOrigPointerField'];
        $languageField = $GLOBALS['TCA'][$this->tableName]['ctrl']['languageField'];

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($this->tableName);
        $queryBuilder->select('*')->from($this->tableName)->setMaxResults(1);
        $queryBuilder->where($queryBuilder->expr()->eq($languageField, $queryBuilder->createNamedParameter($languageUid, \PDO::PARAM_INT)));

        if (VersionNumberUtility::convertVersionNumberToInteger(TYPO3_version) >= 9000000) {
            $this->applyAdminPanelConfiguration($queryBuilder);
        }

        switch ($languageUid) {
            case 0:
                // WHERE sys_language_uid = 0 AND uid = 1000
                $queryBuilder
                    ->andWhere($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($this->getTranslationPointer($transOrigPointerField, $languageField, $languageUid), \PDO::PARAM_INT)));
                break;
            case -1:
                break;
            default:
                // WHERE sys_language_uid = 1 AND l10n_source = 1000
                $queryBuilder->andWhere($queryBuilder->expr()->eq($transOrigPointerField, $queryBuilder->createNamedParameter($this->value, \PDO::PARAM_INT)));
        }

        return !empty($queryBuilder->execute()->fetchAll());
    }

    protected function applyAdminPanelConfiguration(QueryBuilder &$queryBuilder)
    {
        try {
            $userAspect = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Context\\Context')->getAspect('backend.user');
        } catch (\Exception $exception) {
            return;
        }

        if ($userAspect->isLoggedIn()) {
            $user = $GLOBALS['BE_USER'];

            if (isset($user->uc['AdminPanel']) && !empty($user->uc['AdminPanel']) && (bool)$user->uc['AdminPanel']['display_top'] === true) {
                $adminPanelConfig = $user->uc['AdminPanel'];
                $showHiddenPages = (bool)$adminPanelConfig['preview_showHiddenPages'];
                $showHiddenRecords = (bool)$adminPanelConfig['preview_showHiddenRecords'];

                if ($this->tableName === 'pages' && $showHiddenPages === true) {
                    $queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class);
                } elseif ($this->tableName !== 'pages' && $showHiddenRecords === true) {
                    $queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class);
                }
            }
        }
    }

    protected function getTranslationPointer(string $pointerField, string $languageField, int $languageUid): int
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($this->tableName);

        if (VersionNumberUtility::convertVersionNumberToInteger(TYPO3_version) >= 9000000) {
            $this->applyAdminPanelConfiguration($queryBuilder);
        }

        return (int)$queryBuilder
            ->select($pointerField)
            ->from($this->tableName)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($this->value, \PDO::PARAM_INT)))
            ->andWhere($queryBuilder->expr()->eq($languageField, $queryBuilder->createNamedParameter($languageUid, \PDO::PARAM_INT)))
            ->setMaxResults(1)
            ->execute()
            ->fetchColumn(0);
    }

    protected function isTranslatedRecord(int $languageUid): bool
    {
        $record = BackendUtility::getRecord($this->tableName, $this->value);
        $this->translationChecked = true;

        if ($record !== null && is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_page.php']['pageOverlayRecordIsTranslated'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_page.php']['pageOverlayRecordIsTranslated'] as $classRef) {
                $hookObject = GeneralUtility::getUserObj($classRef);

                if (!$hookObject instanceof PageOverlayRecordIsTranslatedInterface) {
                    throw new \UnexpectedValueException($classRef . ' must implement interface ' . PageOverlayRecordIsTranslatedInterface::class, 1558689867);
                }

                $hookObject->isTranslatedRecord($record, $languageUid, $this);
            }
        }

        if ($record !== null && isset($record['sys_language_uid']) && (int)$record['sys_language_uid'] === $languageUid) {
            // Hide page in default language
            $GLOBALS['TSFE']->page['l18n_cfg'] == 0 ? $GLOBALS['TSFE']->page['l18n_cfg'] = 1 : $GLOBALS['TSFE']->page['l18n_cfg'] = 3;

            return false;
        }

        return true;
    }
}
