<?php
declare(strict_types=1);
namespace Bitmotion\Languagemod\Hooks;

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

    public function getPageOverlay_preProcess(&$pageInput, &$lUid, PageRepository $parent)
    {
        $this->initialize();

        if (!empty($this->setup) && isset($this->setup['config.']['tx_languagemod.']) && !empty($this->setup['config.']['tx_languagemod.'])) {
            $config = $this->setup['config.']['tx_languagemod.'];
            $languages = $this->languages ?? $this->getLanguages($config);

            if (in_array($lUid, $languages)) {
                $pages = $this->pages ?? $this->getPages($config);

                if (empty($pages) || in_array($GLOBALS['TSFE']->id, $pages)) {
                    $params = $this->params ?? $this->getParameters($config);
                    $queryParams = $this->queryParams ?? $this->getQueryParams();

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

        foreach ($config['params.'] as $param) {
            if (isset($param['name']) && !empty($param['name'])) {
                if (strpos($param['name'], '.') !== false) {
                    $getVars = explode('.', $param['name']);
                    $this->tableName = $param['table'];
                    $parameters[] = $this->enrichParameters($getVars);
                }
            }
        }

        $this->params = $parameters;

        return $parameters;
    }

    protected function enrichParameters(array $getVars = [], array &$parameters = []): array
    {
        if (is_array($getVars)) {
            $key = array_shift($getVars);
            $parameters[$key] = [];

            if (!empty($getVars)) {
                $this->enrichParameters($getVars, $parameters[$key]);
            }
        }

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
        foreach ($parameters as $parameter) {
            if (!empty($parameter)) {
                $key = array_shift(array_keys($parameter));
                if (isset($queryParameters[$key])) {
                    if (is_array($queryParameters[$key]) && !empty($parameter[$key])) {
                        return $this->requestHasParam($queryParameters[$key], [$parameter[$key]]);
                    }

                    $this->value = (int)$queryParameters[$key];

                    return true;
                }
            }
        }

        return false;
    }

    protected function translationExist(int $languageUid): bool
    {
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
                $queryBuilder
                    ->andWhere($queryBuilder->expr()->eq($transOrigPointerField, $queryBuilder->createNamedParameter($this->value, \PDO::PARAM_INT)));
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
}
