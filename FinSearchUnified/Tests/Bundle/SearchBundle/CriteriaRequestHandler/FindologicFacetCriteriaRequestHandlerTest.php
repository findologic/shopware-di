<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic\ConditionHandler;

use Enlight_Components_Session_Namespace as Session;
use Enlight_Controller_Front as Front;
use Enlight_Controller_Request_RequestHttp as RequestHttp;
use FinSearchUnified\Bundle\SearchBundle\CriteriaRequestHandler\FindologicFacetCriteriaRequestHandler;
use FinSearchUnified\Bundle\StoreFrontBundle\Service\Core\CustomFacetService;
use FinSearchUnified\Bundle\StoreFrontBundle\Struct\Search\CustomFacet;
use FinSearchUnified\Constants;
use FinSearchUnified\Helper\StaticHelper;
use FinSearchUnified\Tests\TestCase;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\Facet\ProductAttributeFacet;
use Shopware_Components_Config as Config;

class FindologicFacetCriteriaRequestHandlerTest extends TestCase
{
    protected function tearDown()
    {
        parent::tearDown();

        Shopware()->Container()->reset('front');
        Shopware()->Container()->load('front');
        Shopware()->Container()->reset('session');
        Shopware()->Container()->load('session');
        Shopware()->Container()->reset('config');
        Shopware()->Container()->load('config');
    }

    public function handleRequestDataProvider()
    {
        {
            return [
                'UseShopSearch is True' => [
                    'ActivateFindologic' => false,
                    'ShopKey' => '0000000000000000ZZZZZZZZZZZZZZZZ',
                    'ActivateFindologicForCategoryPages' => true,
                    'findologicDI' => false,
                    'isSearchPage' => null,
                    'isCategoryPage' => null,
                    'expected' => true,
                ],
                'UseShopSearch is False' => [
                    'ActivateFindologic' => true,
                    'ShopKey' => '0000000000000000ZZZZZZZZZZZZZZZZ',
                    'ActivateFindologicForCategoryPages' => false,
                    'findologicDI' => false,
                    'isSearchPage' => true,
                    'isCategoryPage' => false,
                    'expected' => false
                ],
                'UseShopSearch is False And Category Page is True' => [
                    'ActivateFindologic' => true,
                    'ShopKey' => '0000000000000000ZZZZZZZZZZZZZZZZ',
                    'ActivateFindologicForCategoryPages' => true,
                    'findologicDI' => false,
                    'isSearchPage' => false,
                    'isCategoryPage' => true,
                    'expected' => false
                ],
            ];
            }
    }

    /**
     * @dataProvider handleRequestDataProvider
     *
     * @param bool $isActive
     * @param string $shopKey
     * @param bool $isActiveForCategory
     * @param bool $checkIntegration
     * @param bool $isSearchPage
     * @param bool $isCategoryPage
     * @param bool $expected
     */
    public function testHandleRequestWithoutCriteria(
        $isActive,
        $shopKey,
        $isActiveForCategory,
        $checkIntegration,
        $isSearchPage,
        $isCategoryPage,
        $expected
    ) {
        $configArray = [
            ['ActivateFindologic', $isActive],
            ['ShopKey', $shopKey],
            ['ActivateFindologicForCategoryPages', $isActiveForCategory],
            ['IntegrationType', $checkIntegration ? Constants::INTEGRATION_TYPE_DI : Constants::INTEGRATION_TYPE_API]
        ];

        $categoryId = 5;

        $request = new RequestHttp();
        $request->setModuleName('frontend');
        if ($isSearchPage === true) {
            $request->setParam('sSearch', 'text');
        }
        if ($isCategoryPage === true) {
            $request->setControllerName('listing');
            $request->setParam('sCategory', $categoryId);
        }
        // Create Mock object for Shopware Front Request
        $front = $this->getMockBuilder(Front::class)
            ->setMethods(['Request'])
            ->disableOriginalConstructor()
            ->getMock();
        $front->expects($this->exactly(6))
            ->method('Request')
            ->willReturn($request);
        $context = Shopware()->Container()->get('shopware_storefront.context_service')->getShopContext();
        // Assign mocked session variable to application container
        Shopware()->Container()->set('front', $front);

        if ($isSearchPage !== null) {
            $sessionArray = [
                ['isSearchPage', $isSearchPage],
                ['isCategoryPage', $isCategoryPage],
                ['findologicDI', $checkIntegration]
            ];

            // Create Mock object for Shopware Session
            $session = $this->getMockBuilder(Session::class)
                ->setMethods(['offsetGet'])
                ->getMock();
            $session->expects($this->atLeastOnce())
                ->method('offsetGet')
                ->willReturnMap($sessionArray);

            // Assign mocked session variable to application container
            Shopware()->Container()->set('session', $session);
        }
        // Create Mock object for Shopware Config
        $config = $this->getMockBuilder(Config::class)
            ->setMethods(['offsetGet', 'offsetExists'])
            ->disableOriginalConstructor()
            ->getMock();
        $config->expects($this->atLeastOnce())
            ->method('offsetGet')
            ->willReturnMap($configArray);
        $config->method('offsetExists')
            ->willReturn(true);
        // Assign mocked config variable to application container
        Shopware()->Container()->set('config', $config);

        $result = StaticHelper::useShopSearch();
        $error = 'Expected %s search to be triggered but it was not';
        $shop = $expected ? 'shop' : 'FINDOLOGIC';
        $this->assertEquals($expected, $result, sprintf($error, $shop));

        // Assign mocked CustomFacetService::getList method
        $customFacetServiceMock = $this->createMock(CustomFacetService::class);
        if ($isSearchPage === null) {
            $customFacetServiceMock->expects($this->never())->method('getList');
        }
        if ($isSearchPage === true) {
            $customFacetServiceMock->expects($this->once())->method('getList')->with([])->willReturn([]);
        }
        if ($isCategoryPage === true) {
            $customFacetServiceMock->expects($this->once())
                ->method('getFacetsOfCategories')
                ->with([$categoryId])
                ->willReturn([]);
        }
        $findologicFacet = new FindologicFacetCriteriaRequestHandler($customFacetServiceMock);
        $findologicFacet->handleRequest($request, new Criteria(), $context);
    }

    public function handleRequestSearchProvider()
    {
        return [
            'Passed criteria object still doesnt have any conditions' => [
                'field' => 'vendor',
                'mode' => ProductAttributeFacet::MODE_RADIO_LIST_RESULT,
                'formFieldName' => 'vendor',
                'label' => 'Manufacturer',
                'parameter' => [],
                'value' => null,
                'hasCondition' => false
            ],
            'Passed criteria object has a Field is vendor and Value is Shopware Food' => [
                'field' => 'vendor',
                'mode' => ProductAttributeFacet::MODE_RADIO_LIST_RESULT,
                'formFieldName' => 'vendor',
                'label' => 'Manufacturer',
                'parameter' => ['vendor' => 'Shopware Food'],
                'value' => 'Shopware Food',
                'hasCondition' => true
            ],
            'Passed criteria object has a Field is vendor and  Value is array [Shopware Food, Shopware Freetime]' => [
                'field' => 'vendor',
                'mode' => ProductAttributeFacet::MODE_VALUE_LIST_RESULT,
                'formFieldName' => 'vendor',
                'label' => 'Manufacturer',
                'parameter' => ['vendor' => 'Shopware Food|Shopware Freetime'],
                'value' => ['Shopware Food', 'Shopware Freetime'],
                'hasCondition' => true
            ],
            'Passed criteria object has a condition of type Field is size and Value is array' => [
                'field' => 'size',
                'mode' => ProductAttributeFacet::MODE_RADIO_LIST_RESULT,
                'formFieldName' => 'size',
                'label' => 'Size',
                'parameter' => ['size' => ['min' => 'S', 'max' => 'L']],
                'value' => ['min' => 'S', 'max' => 'L'],
                'hasCondition' => true
            ],
        ];
    }

    /**
     * @dataProvider handleRequestSearchProvider
     *
     * @param string $field
     * @param string $mode
     * @param string $formFieldName
     * @param string $label
     * @param array $parameter
     * @param mixed $value
     * @param bool $hasCondition
     */
    public function testHandleRequestForSearchPage(
        $field,
        $mode,
        $formFieldName,
        $label,
        array $parameter,
        $value,
        $hasCondition
    ) {
        $configArray = [
            ['ActivateFindologic', true],
            ['ShopKey', '0000000000000000ZZZZZZZZZZZZZZZZ'],
            ['ActivateFindologicForCategoryPages', false],
            ['IntegrationType', Constants::INTEGRATION_TYPE_API]
        ];
        $criteria = new Criteria();
        $request = new RequestHttp();
        $request->setModuleName('frontend');
        $request->setParam('sSearch', 'text');
        $request->setParams($parameter);
        $productAttributeFacet = new ProductAttributeFacet($field, $mode, $formFieldName, $label);
        $customFacet = new CustomFacet();
        $customFacet->setFacet($productAttributeFacet);

        // Create Mock object for Shopware Front Request
        $front = $this->getMockBuilder(Front::class)
            ->setMethods(['Request'])
            ->disableOriginalConstructor()
            ->getMock();
        $front->expects($this->exactly(6))
            ->method('Request')
            ->willReturn($request);
        $context = Shopware()->Container()->get('shopware_storefront.context_service')->getShopContext();
        // Assign mocked session variable to application container
        Shopware()->Container()->set('front', $front);

        $sessionArray = [
            ['isSearchPage', true],
            ['findologicDI', false]
        ];
        // Create Mock object for Shopware Session
        $session = $this->getMockBuilder(Session::class)->getMock();
        $session->expects($this->atLeastOnce())
            ->method('offsetGet')
            ->willReturnMap($sessionArray);
        // Assign mocked session variable to application container
        Shopware()->Container()->set('session', $session);

        // Create Mock object for Shopware Config
        $config = $this->getMockBuilder(Config::class)
            ->setMethods(['offsetGet', 'offsetExists'])
            ->disableOriginalConstructor()
            ->getMock();
        $config->expects($this->atLeastOnce())
            ->method('offsetGet')
            ->willReturnMap($configArray);
        // Assign mocked config variable to application container
        Shopware()->Container()->set('config', $config);

        $result = StaticHelper::useShopSearch();
        $this->assertFalse($result, 'Expected FINDOLOGIC search to be triggered but it was not');

        $customFacetServiceMock = $this->createMock(CustomFacetService::class);
        $customFacetServiceMock->expects($this->once())->method('getList')->willReturn([$customFacet]);

        $facetCriteriaRequestHandler = new FindologicFacetCriteriaRequestHandler($customFacetServiceMock);
        $facetCriteriaRequestHandler->handleRequest($request, $criteria, $context);

        $condition = $criteria->getConditions();
        if ($hasCondition === false) {
            $this->assertEmpty($condition);
        }
        if ($hasCondition === true) {
            $this->assertNotEmpty($condition);
            $this->assertTrue($criteria->hasCondition('product_attribute_' . $field));
            $condition = $criteria->getCondition('product_attribute_' . $field);
            $conditionField = $condition->getField();
            $conditionValue = $condition->getValue();

            $this->assertEquals($field, $conditionField);
            $this->assertEquals($value, $conditionValue);
        }
    }
}
