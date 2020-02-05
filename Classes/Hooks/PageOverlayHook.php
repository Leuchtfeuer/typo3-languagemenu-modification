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

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\FrontendConfigurationManager;
use TYPO3\CMS\Frontend\Page\PageRepository;
use TYPO3\CMS\Frontend\Page\PageRepositoryGetPageOverlayHookInterface;

class PageOverlayHook implements PageRepositoryGetPageOverlayHookInterface, SingletonInterface
{
    /**
     * @var int[]
     */
    protected $languages = [];

    /**
     * @var int[]
     */
    protected $pages = [];

    /**
     * @var FrontendConfigurationManager
     */
    protected $configurationManager;

    protected $parameters = [];

    protected $queryParameters = [];

    protected $canHandle = false;

    protected $value = 0;

    protected $tableName = '';

    protected $translationChecked = false;

    protected $initialized = false;

    public function __construct()
    {
        $this->configurationManager = GeneralUtility::makeInstance(FrontendConfigurationManager::class);
    }

    public function getPageOverlay_preProcess(&$pageInput, &$lUid, PageRepository $parent)
    {
        $this->initialize();

        if ($this->canHandle === true) {
            // Get current language uid from language aspect
            $languageId = GeneralUtility::makeInstance(Context::class)->getAspect('language')->getId();

            // Skip when we are in current language and we already checked whether given record is a translation
            if ($lUid === $languageId && $this->translationChecked === true) {
                return;
            }

            // Do not overlay page as record is not translated
            if (
                in_array($lUid, $this->languages)
                && (empty($this->pages) || in_array($GLOBALS['TSFE']->id, $this->pages))
                && $this->requestHasParameter()
                && !$this->translationExist($lUid)
            ) {
                $lUid = 0;
            }
        }
    }

    protected function initialize(): void
    {
        if (!$this->initialized) {
            $setup = $this->configurationManager->getTypoScriptSetup();
            if (!empty($setup)) {
                $this->initialized = true;

                if (!empty($setup['config.']['tx_languagemod.'])) {
                    $config = $setup['config.']['tx_languagemod.'];
                    $this->setLanguages($config);
                    $this->setPages($config);
                    $this->setParameters($config);
                    $this->setQueryParameters();
                    $this->canHandle = true;
                }
            }
        }
    }

    protected function setLanguages(array $config): void
    {
        $this->languages = GeneralUtility::intExplode(',', $config['languages'], true);
    }

    protected function setPages(array $config): void
    {
        $this->pages = GeneralUtility::intExplode(',', $config['pages'], true);
    }

    protected function setParameters(array $config): void
    {
        $parameters = [];

        foreach ($config['params.'] as $tableName => $param) {
            $getVars = explode('.', $param);
            $parameters[] = [
                'tableName' => $tableName,
                'getVars' => array_combine($getVars, $getVars),
            ];
        }

        $this->parameters = $parameters;
    }

    protected function setQueryParameters(): void
    {
        $this->queryParameters = $GLOBALS['TYPO3_REQUEST']->getQueryParams();
    }

    protected function requestHasParameter(): bool
    {
        foreach ($this->parameters as $key => $parameter) {
            $value = 0;
            $paramsToIterate = $this->queryParameters;

            foreach ($parameter['getVars'] ?? [] as $getVar) {
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
        $this->applyAdminPanelConfiguration($queryBuilder);

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
                $queryBuilder
                    ->andWhere($queryBuilder->expr()->eq($transOrigPointerField, $queryBuilder->createNamedParameter($this->value, \PDO::PARAM_INT)));
        }

        return $queryBuilder->execute()->rowCount() !== 0;
    }

    protected function applyAdminPanelConfiguration(QueryBuilder &$queryBuilder): void
    {
        try {
            $backendUserAspect = GeneralUtility::makeInstance(Context::class)->getAspect('backend.user');
        } catch (AspectNotFoundException $exception) {
            return;
        }

        if ($backendUserAspect->isLoggedIn()) {
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
        $this->applyAdminPanelConfiguration($queryBuilder);

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

        // Skip check for records in all languages
        if (isset($record['sys_language_uid']) && $record['sys_language_uid'] === -1) {
            return false;
        }

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
