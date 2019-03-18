<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\SortingHandler;

use FinSearchUnified\Bundle\SearchBundleFindologic\FindologicSortingHandlerInterface;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder;
use Shopware\Bundle\SearchBundle\Sorting\PopularitySorting;
use Shopware\Bundle\SearchBundle\SortingInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

class PopularitySortingHandler implements FindologicSortingHandlerInterface
{
    /**
     * Checks if the passed sorting can be handled by this class
     *
     * @param SortingInterface $sorting
     *
     * @return bool
     */
    public function supportsSorting(SortingInterface $sorting)
    {
        return $sorting instanceof PopularitySorting;
    }

    /**
     * Handles the passed sorting object.
     * Extends the passed query builder with the specify sorting.
     * Should use the addOrderBy function, otherwise other sortings would be overwritten.
     *
     * @param SortingInterface $sorting
     * @param QueryBuilder $query
     * @param ShopContextInterface $context
     */
    public function generateSorting(SortingInterface $sorting, QueryBuilder $query, ShopContextInterface $context)
    {
        /** @var PopularitySorting $sorting */
        $query->addOrder('salesfrequency ' . $sorting->getDirection());
    }
}
