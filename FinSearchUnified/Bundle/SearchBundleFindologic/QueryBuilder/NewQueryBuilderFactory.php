<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder;

use Exception;
use FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler\CategoryConditionHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler\PriceConditionHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler\ProductAttributeConditionHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler\SearchTermConditionHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler\SimpleConditionHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandlerInterface;
use FinSearchUnified\Bundle\SearchBundleFindologic\SortingHandler\PopularitySortingHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\SortingHandler\PriceSortingHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\SortingHandler\ProductNameSortingHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\SortingHandler\ReleaseDateSortingHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\SortingHandlerInterface;
use Shopware\Bundle\PluginInstallerBundle\Service\InstallerService;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\SortingInterface;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilderFactoryInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;
use Shopware_Components_Config;

class NewQueryBuilderFactory implements QueryBuilderFactoryInterface
{
    /**
     * @var InstallerService
     */
    private $installerService;

    /**
     * @var Shopware_Components_Config
     */
    private $config;

    /**
     * @var SortingHandlerInterface[]
     */
    private $sortingHandlers;

    /**
     * @var ConditionHandlerInterface[]
     */
    private $conditionHandlers;

    public function __construct(
        InstallerService $installerService,
        Shopware_Components_Config $config
    ) {
        $this->installerService = $installerService;
        $this->config = $config;

        $this->sortingHandlers = $this->registerSortingHandlers();
        $this->conditionHandlers = $this->registerConditionHandlers();
    }

    /**
     * @return SortingHandlerInterface[]
     */
    private function registerSortingHandlers()
    {
        $sortingHandlers = [];

        $sortingHandlers[] = new PopularitySortingHandler();
        $sortingHandlers[] = new PriceSortingHandler();
        $sortingHandlers[] = new ProductNameSortingHandler();
        $sortingHandlers[] = new ReleaseDateSortingHandler();

        return $sortingHandlers;
    }

    /**
     * @return ConditionHandlerInterface[]
     */
    private function registerConditionHandlers()
    {
        $conditionHandlers = [];

        $conditionHandlers[] = new CategoryConditionHandler();
        $conditionHandlers[] = new PriceConditionHandler();
        $conditionHandlers[] = new ProductAttributeConditionHandler();
        $conditionHandlers[] = new SearchTermConditionHandler();
        $conditionHandlers[] = new SimpleConditionHandler();

        return $conditionHandlers;
    }

    /**
     * @param ConditionInterface $condition
     *
     * @return ConditionHandlerInterface|null
     */
    private function getConditionHandler(ConditionInterface $condition)
    {
        foreach ($this->conditionHandlers as $handler) {
            if ($handler->supportsCondition($condition)) {
                return $handler;
            }
        }

        return null;
    }

    /**
     * @param Criteria $criteria
     * @param NewQueryBuilder $query
     * @param ShopContextInterface $context
     */
    private function addConditions(Criteria $criteria, NewQueryBuilder $query, ShopContextInterface $context)
    {
        foreach ($criteria->getConditions() as $condition) {
            $handler = $this->getConditionHandler($condition);
            if ($handler !== null) {
                $handler->generateCondition($condition, $query, $context);
            }
        }
    }

    /**
     * @param SortingInterface $sorting
     *
     * @return SortingHandlerInterface|null
     */
    private function getSortingHandler(SortingInterface $sorting)
    {
        foreach ($this->sortingHandlers as $handler) {
            if ($handler->supportsSorting($sorting)) {
                return $handler;
            }
        }

        return null;
    }

    /**
     * @param Criteria $criteria
     * @param NewQueryBuilder $query
     * @param ShopContextInterface $context
     */
    private function addSorting(Criteria $criteria, NewQueryBuilder $query, ShopContextInterface $context)
    {
        foreach ($criteria->getSortings() as $sorting) {
            $handler = $this->getSortingHandler($sorting);
            if ($handler !== null) {
                $handler->generateSorting($sorting, $query, $context);
            }
        }
    }

    /**
     * Creates the product number search query for the provided
     * criteria and context.
     * Adds the sortings and conditions of the provided criteria.
     *
     * @param Criteria $criteria
     * @param ShopContextInterface $context
     *
     * @return NewQueryBuilder
     * @throws Exception
     */
    public function createQueryWithSorting(Criteria $criteria, ShopContextInterface $context)
    {
        $query = $this->createQuery($criteria, $context);

        $this->addSorting($criteria, $query, $context);

        return $query;
    }

    /**
     * Generates the product selection query of the product number search
     *
     * @param Criteria $criteria
     * @param ShopContextInterface $context
     *
     * @return NewQueryBuilder
     * @throws Exception
     */
    public function createProductQuery(Criteria $criteria, ShopContextInterface $context)
    {
        $query = $this->createQueryWithSorting($criteria, $context);
        $query->setFirstResult($criteria->getOffset());

        if ($criteria->getOffset() === 0 && $criteria->getLimit() === 1) {
            $limit = 0;
        } else {
            $limit = $criteria->getLimit();
        }

        $query->setMaxResults($limit);

        return $query;
    }

    /**
     * Creates the product number search query for the provided
     * criteria and context.
     * Adds only the conditions of the provided criteria.
     *
     * @param Criteria $criteria
     * @param ShopContextInterface $context
     *
     * @return NewQueryBuilder
     * @throws Exception
     */
    public function createQuery(Criteria $criteria, ShopContextInterface $context)
    {
        $query = $this->createQueryBuilder();
        $query->addUserGroup($context->getCurrentCustomerGroup()->getKey());
        $this->addConditions($criteria, $query, $context);

        return $query;
    }

    /**
     * @return NewQueryBuilder
     */
    public function createQueryBuilder()
    {
        $isSearchPage = Shopware()->Session()->offsetGet('isSearchPage');

        if ($isSearchPage) {
            $querybuilder = new NewSearchQueryBuilder(
                $this->installerService,
                $this->config
            );
        } else {
            $querybuilder = new NewNavigationQueryBuilder(
                $this->installerService,
                $this->config
            );
        }

        return $querybuilder;
    }

    /**
     * @param Criteria $criteria
     * @param ShopContextInterface $context
     *
     * @return NewQueryBuilder
     * @throws Exception
     */
    public function createSearchNavigationQueryWithoutAdditionalFilters(
        Criteria $criteria,
        ShopContextInterface $context
    ) {
        $query = $this->createQueryBuilder();
        $condition = null;

        if ($query instanceof NewSearchQueryBuilder) {
            $condition = $criteria->getCondition('search');
        }
        if ($query instanceof NewNavigationQueryBuilder) {
            $condition = $criteria->getCondition('category');
        }

        if ($condition !== null) {
            $handler = $this->getConditionHandler($condition);
            if ($handler !== null) {
                $handler->generateCondition($condition, $query, $context);
            }
        }

        return $query;
    }
}