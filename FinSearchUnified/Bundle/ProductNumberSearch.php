<?php

namespace FinSearchUnified\Bundle;

use Exception;
use FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\CategoryFacetHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\ColorFacetHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\ImageFacetHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\RangeFacetHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\TextFacetHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\PartialFacetHandlerInterface;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder;
use FinSearchUnified\Helper\StaticHelper;
use Shopware\Bundle\SearchBundle\Condition\PriceCondition;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\Facet\ProductAttributeFacet;
use Shopware\Bundle\SearchBundle\FacetInterface;
use Shopware\Bundle\SearchBundle\ProductNumberSearchInterface;
use Shopware\Bundle\SearchBundle\ProductNumberSearchResult;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilderFactoryInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;
use SimpleXMLElement;

class ProductNumberSearch implements ProductNumberSearchInterface
{
    /**
     * @var ProductNumberSearchInterface
     */
    protected $originalService;

    /**
     * @var QueryBuilderFactoryInterface
     */
    protected $queryBuilderFactory;

    /**
     * @var PartialFacetHandlerInterface[]
     */
    private $facetHandlers;

    /**
     * @param ProductNumberSearchInterface $service
     * @param QueryBuilderFactoryInterface $queryBuilderFactory
     */
    public function __construct(
        ProductNumberSearchInterface $service,
        QueryBuilderFactoryInterface $queryBuilderFactory
    ) {
        $this->originalService = $service;
        $this->queryBuilderFactory = $queryBuilderFactory;
        $this->facetHandlers = $this->registerFacetHandlers();
    }

    /**
     * Creates a product search result for the passed criteria object.
     * The criteria object contains different core conditions and plugin conditions.
     * This conditions has to be handled over the different condition handlers
     *
     * @param Criteria $criteria
     * @param ShopContextInterface $context
     *
     * @return ProductNumberSearchResult
     * @throws Exception
     */
    public function search(Criteria $criteria, ShopContextInterface $context)
    {
        // Shopware sets fetchCount to false when the search is used for internal purposes, which we don't care about.
        // Checking its value is the only way to tell if we should actually perform the search.
        $fetchCount = $criteria->fetchCount();

        $useShopSearch = StaticHelper::useShopSearch();

        if (!$fetchCount || $useShopSearch) {
            return $this->originalService->search($criteria, $context);
        }

        /** @var QueryBuilder $query */
        $query = $this->queryBuilderFactory->createProductQuery($criteria, $context);
        $response = $query->execute();

        if (empty($response)) {
            self::setFallbackFlag(1);
            $searchResult = $this->originalService->search($criteria, $context);
        } else {
            self::setFallbackFlag(0);
            $xmlResponse = StaticHelper::getXmlFromResponse($response);
            self::redirectOnLandingpage($xmlResponse);
            StaticHelper::setPromotion($xmlResponse);
            StaticHelper::setSmartDidYouMean($xmlResponse);

            $totalResults = (int)$xmlResponse->results->count;
            $foundProducts = StaticHelper::getProductsFromXml($xmlResponse);
            $searchResult = StaticHelper::getShopwareArticlesFromFindologicId($foundProducts);
            $facets = $this->createFacets($criteria, $xmlResponse->filters->filter);

            $searchResult = new ProductNumberSearchResult($searchResult, $totalResults, $facets);
        }

        return $searchResult;
    }

    /**
     * Checks if a landing page is present in the response and in that case, performs a redirect.
     *
     * @param SimpleXMLElement $xmlResponse
     */
    protected static function redirectOnLandingpage(SimpleXMLElement $xmlResponse)
    {
        $hasLandingpage = StaticHelper::checkIfRedirect($xmlResponse);

        if ($hasLandingpage != null) {
            header('Location: ' . $hasLandingpage);
            exit();
        }
    }

    /**
     * Sets a browser cookie with the given value.
     *
     * @param bool $status
     */
    protected static function setFallbackFlag($status)
    {
        setcookie('Fallback', $status, 0, '', '', true);
    }

    private function registerFacetHandlers()
    {
        return [
            $CategoryFacetHandler = new CategoryFacetHandler(),
            $ColorFacetHandler = new ColorFacetHandler(),
            $ImageFacetHandler = new ImageFacetHandler(
                Shopware()->Container()->get('guzzle_http_client_factory'),
                []
            ),
            $RangeFacetHandler = new RangeFacetHandler(),
            $TextFacetHandler = new TextFacetHandler(),
        ];
    }

    private function getFacetHandler(SimpleXMLElement $filter)
    {
        foreach ($this->facetHandlers as $handler) {
            if ($handler->supportsFilter($filter)) {
                return $handler;
            }
        }

        return null;
    }

    protected function createFacets(Criteria $criteria, SimpleXMLElement $filters)
    {
        $facets = [];

        foreach ($criteria->getFacets() as $criteriaFacet) {
            $field = $criteriaFacet->getField();
            $facetName = $criteriaFacet->getName();
            $selectedFilter = $filters->xpath(sprintf('//name[.="%s"]/parent::*', $field));

            if (empty($selectedFilter)) {
                if (!$criteria->hasUserCondition($facetName)) {
                    continue;
                }

                if ($facetName === 'price' || $field === 'price') {
                    $selectedFilter = $this->createSelectedFilter($criteriaFacet, $criteria->getCondition($facetName));
                }
            } else {
                $selectedFilter = $selectedFilter[0];
            }

            $handler = $this->getFacetHandler($selectedFilter);
            if ($handler === null) {
                continue;
            }
            $partialFacet = $handler->generatePartialFacet($criteriaFacet, $criteria, $selectedFilter);
            if ($partialFacet === null) {
                continue;
            }
            $facets[] = $partialFacet;
        }

        return $facets;
    }

    /**
     * @param FacetInterface $facet
     * @param ConditionInterface $condition
     *
     * @return SimpleXMLElement
     */
    private function createSelectedFilter(FacetInterface $facet, ConditionInterface $condition)
    {
        if ($condition instanceof PriceCondition) {
            $filter = new SimpleXMLElement('');
            $filter->addChild('name', $condition->getField());
            $filter->addChild('type', 'range-slider');
            $attributes = $filter->addChild('attributes');
            $totalRange = $attributes->addChild('totalRange');
            $totalRange->addChild('min', $condition->getMinPrice());
            $totalRange->addChild('max', $condition->getMaxPrice() ?: PHP_INT_MAX);
            $selectedRange = $attributes->addChild('selectedRange');
            $selectedRange->addChild('min', $condition->getMinPrice());
            $selectedRange->addChild('max', $condition->getMaxPrice() ?: PHP_INT_MAX);

            return $filter;
        }

        if ($facet->getMode() === ProductAttributeFacet::MODE_RANGE_RESULT) {
            $values = $condition->getValues();
            $filter = new SimpleXMLElement('');
            $filter->addChild('name', $condition->getField());
            $filter->addChild('type', 'range-slider');
            $attributes = $filter->addChild('attributes');
            $totalRange = $attributes->addChild('totalRange');
            $totalRange->addChild('min', isset($values['min']) ? $values['min'] : 0);
            $totalRange->addChild('max', isset($values['max']) ? $values['max'] : PHP_INT_MAX);
            $selectedRange = $attributes->addChild('selectedRange');
            $selectedRange->addChild('min', isset($values['min']) ? $values['min'] : 0);
            $selectedRange->addChild('max', isset($values['max']) ? $values['max'] : PHP_INT_MAX);

            return $filter;
        }

        $filter = new SimpleXMLElement('');
        $filter->addChild('name', $condition->getField());
        $filter->addChild('type', 'label');
        $filter->addChild('items');

        return $filter;
    }
}
