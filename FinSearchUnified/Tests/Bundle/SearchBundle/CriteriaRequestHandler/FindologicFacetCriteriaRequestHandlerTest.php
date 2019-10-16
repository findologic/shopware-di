<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic\ConditionHandler;

use Enlight_Components_Session_Namespace as Session;
use Enlight_Controller_Request_RequestHttp as RequestHttp;
use FinSearchUnified\Bundle\SearchBundle\Condition\ProductAttributeCondition;
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

    public function testHandleRequest(
        $isActive,
        $shopKey,
        $isActiveForCategory,
        $checkIntegration,
        $isSearchPage,
        $isCategoryPage,
        $expected){
        $configArray = [
            ['ActivateFindologic', $isActive],
            ['ShopKey', $shopKey],
            ['ActivateFindologicForCategoryPages', $isActiveForCategory],
            ['IntegrationType', $checkIntegration ? Constants::INTEGRATION_TYPE_DI : Constants::INTEGRATION_TYPE_API]
            ];
            $criteria = new Criteria();
            $request = new RequestHttp();
            $request->setModuleName('frontend');
            if($isSearchPage === true){
                $request->setParam('sSearch','text');
            }
            if($isCategoryPage === true){
                $request->setControllerName('listing');
                $request->setParam('sCategory',5);
            }
            // Create Mock object for Shopware Front Request
            $front = $this->getMockBuilder(Front::class)
                ->setMethods(['Request'])
                ->disableOriginalConstructor()
                ->getMock();
            $front->expects($this->any())
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
            $config->expects($this->any())
                ->method('offsetExists')
                ->willReturn(true);

            // Assign mocked config variable to application container
            Shopware()->Container()->set('config', $config);

            $result = StaticHelper::useShopSearch();
            $error = 'Expected %s search to be triggered but it was not';
            $shop = $expected ? 'shop' : 'FINDOLOGIC';
            $this->assertEquals($expected, $result, sprintf($error, $shop));

            // Assign mocked CustomFacetService::getList method
            $customFacetServiceMock = $this->createMock(CustomFacetService::class);
            if($isSearchPage == null){
                $customFacetServiceMock->expects($this->never())->method('getList');
            } if($isSearchPage == true) {
            $customFacetServiceMock->expects($this->once())->method('getList')->with([]);
            }
            if($isCategoryPage == true){
                $customFacetServiceMock->expects($this->once())->method('getFacetsOfCategories')->with([5]);
            }
        $findologicFacet = new FindologicFacetCriteriaRequestHandler($customFacetServiceMock);
        $findologicFacet->handleRequest($request , $criteria , $context);
    }



    public function HandleRequestFacetDataProvider()
    {
        return [
            'Passed criteria object still doesnt have any conditions' => [
                'field'=>'vendor',
                'mode' => ProductAttributeFacet::MODE_RADIO_LIST_RESULT,
                'formFieldName' =>'vendor',
                'label'=>'Manufacturer'
            ],
            'Passed criteria object has a Field is vendor and Value is Shopware Food' => [
                'field' => 'vendor',
                'mode' => ProductAttributeFacet::MODE_RADIO_LIST_RESULT,
                'formFieldName' =>'Shopware Food',
                'label'=>'Manufacturer'
            ],
            'Passed criteria object has a Field is vendor and  Value is array [Shopware Food, Shopware Freetime]' => [
                'field' => 'vendor',
                'mode' => ProductAttributeFacet::MODE_VALUE_LIST_RESULT,
                'formFieldName' =>'Shopware Food|Shopware Freetime',
                'label'=>'Manufacturer'
            ],
            'Passed criteria object has a condition of type Field is size and Value is array' => [
                'field' => 'size',
                'mode' => ProductAttributeFacet::MODE_RADIO_LIST_RESULT,
                'formFieldName' =>'minsize=S & maxsize=L',
                'label'=>'Size'
            ],
        ];
    }

    /**
     * @dataProvider HandleRequestFacetDataProvider
     *
     * @param bool $field
     * @param string $mode
     * @param bool $formFieldName
     * @param bool $label
     */

    public function testHandleFacet($field,$mode,$formFieldName,$label){
        $isActive = true;
        $shopKey = '0000000000000000ZZZZZZZZZZZZZZZZ';
        $isActiveForCategory = false;
        $checkIntegration = false;
        $isSearchPage = true;
        $expected = false;
            $configArray = [
                ['ActivateFindologic', $isActive],
                ['ShopKey', $shopKey],
                ['ActivateFindologicForCategoryPages', $isActiveForCategory],
                ['IntegrationType', $checkIntegration ? Constants::INTEGRATION_TYPE_DI : Constants::INTEGRATION_TYPE_API]
            ];

//        return $this->parse(
//            $query,
//            's_articles_attributes',
//            'productAttribute',
//            $condition->getField(),
//            $condition->getValue(),
//            $condition->getOperator()
//        );
            $criteria = new Criteria();
            $request = new RequestHttp();
            $request->setModuleName('frontend');
            $request->setParam('sSearch','text');
            $productAttributeFacet = new ProductAttributeFacet( $field, $mode, $formFieldName, $label);
            $customFacet = new customFacet();
            //$ProductAttributeFacet = new ProductAttributeFacet('vendor', ProductAttributeFacet::MODE_RADIO_LIST_RESULT, 'vendor', 'Manufacturer');
            $customFacet->setFacet($productAttributeFacet);

            /** @var ProductAttributeCondition $condition */
         if ($condition = $criteria->getCondition($productAttributeFacet->getName())){
             $value = $condition->getValue();
             var_dump($value);
            }
        if ($condition = $criteria->getCondition($productAttributeFacet->getField() == 'vendor' && $productAttributeFacet->getFormFieldName() == 'Shopware Food')){
            $value = $condition->getValue();
            var_dump($value);
        }
        if ($condition = $criteria->getCondition($productAttributeFacet->getField() == 'vendor' && $productAttributeFacet->getFormFieldName() == '[Shopware Food, Shopware Freetime]')){
            $value = $condition->getValue();
            var_dump($value);
            if (!is_array($value)) {
               $value = [$value];
            }
        }
        // Create Mock object for Shopware Front Request
        $front = $this->getMockBuilder(Front::class)
            ->setMethods(['Request'])
            ->disableOriginalConstructor()
            ->getMock();
        $front->expects($this->any())
            ->method('Request')
            ->willReturn($request);
        $context = Shopware()->Container()->get('shopware_storefront.context_service')->getShopContext();
        // Assign mocked session variable to application container
        Shopware()->Container()->set('front', $front);
        if ($isSearchPage !== null) {
            $sessionArray = [
                ['isSearchPage', $isSearchPage],
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
        $config->expects($this->any())
            ->method('offsetExists')
            ->willReturn(true);
        // Assign mocked config variable to application container
        Shopware()->Container()->set('config', $config);
        $result = StaticHelper::useShopSearch();
        $error = 'Expected %s search to be triggered but it was not';
        $shop = $expected ? 'shop' : 'FINDOLOGIC';
        $this->assertEquals($expected, $result, sprintf($error, $shop));
        // Assign mocked CustomFacetService::getList method
        $customFacetServiceMock = $this->createMock(CustomFacetService::class);
        if($isSearchPage == true) {
            $customFacetServiceMock->expects($this->once())->method('getList')->willReturn([$customFacet]);
        }
        $findologicFacet = new FindologicFacetCriteriaRequestHandler($customFacetServiceMock);
        $findologicFacet->handleRequest($request , $criteria , $context);
    }
}