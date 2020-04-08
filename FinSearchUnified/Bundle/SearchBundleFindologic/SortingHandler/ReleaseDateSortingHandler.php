<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\SortingHandler;

use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\NewQueryBuilder;
use FinSearchUnified\Bundle\SearchBundleFindologic\SortingHandlerInterface;
use Shopware\Bundle\SearchBundle\Sorting\ReleaseDateSorting;
use Shopware\Bundle\SearchBundle\SortingInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

class ReleaseDateSortingHandler implements SortingHandlerInterface
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
        return $sorting instanceof ReleaseDateSorting;
    }

    /**
     * Handles the passed sorting object
     *
     * @param SortingInterface $sorting
     * @param NewQueryBuilder $query
     * @param ShopContextInterface $context
     */
    public function generateSorting(SortingInterface $sorting, NewQueryBuilder $query, ShopContextInterface $context)
    {
        /** @var ReleaseDateSorting $sorting */
        $query->addOrder('dateadded ' . $sorting->getDirection());
    }
}
