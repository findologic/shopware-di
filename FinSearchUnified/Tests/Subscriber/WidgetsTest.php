<?php

namespace FinSearchUnified\Tests\Subscriber;

use Enlight_Controller_ActionEventArgs;
use Enlight_Controller_Request_RequestHttp;
use Enlight_Event_EventArgs;
use Enlight_Hook_HookArgs;
use ReflectionException;
use Shopware_Controllers_Widgets_Listing;
use Zend_Cache_Core;

class WidgetsTest extends SubscriberTestCase
{
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        Shopware()->Container()->get('config_writer')->save('ActivateFindologic', true);
    }

    protected function tearDown()
    {
        parent::tearDown();

        unset(Shopware()->Session()->isSearchPage);
        unset(Shopware()->Session()->isSearchPage);
        Shopware()->Session()->offsetUnset('isSearchPage');
    }

    /**
     * @dataProvider searchParameterProvider
     *
     * @param array $requestParameters
     * @param array $expectedRequestParameters
     * @param string $expectedMessage
     *
     * @throws ReflectionException
     */
    public function testBeforeListingCountActionIfFindologicSearchIsActive(
        array $requestParameters,
        array $expectedRequestParameters,
        $expectedMessage
    ) {
        $request = new Enlight_Controller_Request_RequestHttp();
        $request->setControllerName('listing')
            ->setActionName('listingCount')
            ->setModuleName('widgets')
            ->setParams($requestParameters);

        // Make sure that the findologic search is triggered
        Shopware()->Container()->get('front')->setRequest($request);
        Shopware()->Session()->isSearchPage = true;

        $subject = $this->getControllerInstance(Shopware_Controllers_Widgets_Listing::class, $request);

        // Create mocked args for getting Subject and Request due to backwards compatibility
        $args = $this->createMock(Enlight_Hook_HookArgs::class);
        $args->expects($this->once())->method('getSubject')->willReturn($subject);

        $widgets = Shopware()->Container()->get('fin_search_unified.subscriber.widgets');
        $widgets->beforeListingCountAction($args);

        $params = $subject->Request()->getParams();

        if (empty($expectedRequestParameters)) {
            $this->assertArrayNotHasKey('sSearch', $params, 'Expected no query parameter to be set');
        } else {
            $this->assertSame($expectedRequestParameters, $params, $expectedMessage);
        }
    }

    /**
     * @throws ReflectionException
     */
    public function testBeforeListingCountActionIfShopSearchIsActive()
    {
        $request = new Enlight_Controller_Request_RequestHttp();
        $request->setControllerName('listing')
            ->setActionName('listingCount')
            ->setModuleName('widgets');

        $subject = $this->getControllerInstance(Shopware_Controllers_Widgets_Listing::class, $request);

        // Create mocked args for getting Subject and Request due to backwards compatibility
        $args = $this->createMock(Enlight_Hook_HookArgs::class);
        $args->expects($this->never())->method('getSubject');

        $widgets = Shopware()->Container()->get('fin_search_unified.subscriber.widgets');
        $widgets->beforeListingCountAction($args);

        $params = $subject->Request()->getParams();

        $this->assertArrayNotHasKey('sSearch', $params, 'Expected no query parameter to be set');
        $this->assertArrayNotHasKey('sCategory', $params, 'Expected no category parameter to be set');
    }

    public function searchParameterProvider()
    {
        return [
            'Only category ID 5 was requested' => [
                ['sCategory' => 5],
                [],
                'Expected only "sCategory" to be present'
            ],
            'Only search term "blubbergurke" was requested' => [
                ['sSearch' => 'blubbergurke'],
                ['sSearch' => 'blubbergurke'],
                'Expected only "sSearch" parameter to be present'
            ],
            'Neither search nor category listing was requested' => [
                [],
                ['sSearch' => ' '],
                'Expected "sSearch" to be present with single whitespace character'
            ],
            'Search has an empty string value' => [
                ['sSearch' => ''],
                ['sSearch' => ' '],
                'Expected "sSearch" to be present with single whitespace character'
            ],
        ];
    }

    public function searchPageProvider()
    {
        return [
            'Referer is https://example.com/search/?q=text' => [
                'sSearch' => 'Yes',
                'sCategory' => null,
                'referer' => 'https://example.com/search/?q=text',
                'ExpectedIsSearchPage' => true,
                'ExpectedIsCategoryPage' => false,
            ],
            'Referer is http://example.com/search/?q=text' => [
                'sSearch' => 'Yes',
                'sCategory' => null,
                'referer' => 'http://example.com/search/?q=text',
                'ExpectedIsSearchPage' => true,
                'ExpectedIsCategoryPage' => false,
            ],
            'Referer is https://example.com/search?q=text' => [
                'sSearch' => 'Yes',
                'sCategory' => null,
                'referer' => 'https://example.com/search?q=text',
                'ExpectedIsSearchPage' => true,
                'ExpectedIsCategoryPage' => false,
            ],
            'Referer is https://example.com/search' => [
                'sSearch' => 'Yes',
                'sCategory' => null,
                'referer' => 'https://example.com/search',
                'expectedIsSearchPage' => true,
                'expectedIsCategoryPage' => false,
            ],
            'Referer is https://example.com/search/' => [
                'sSearch' => 'Yes',
                'sCategory' => null,
                'referer' => 'https://example.com/search/',
                'expectedIsSearchPage' => true,
                'expectedIsCategoryPage' => false,
            ],
            'Referer is https://example.com/shop/search/?q=text' => [
                'sSearch' => 'Yes',
                'sCategory' => null,
                'referer' => 'https://example.com/shop/search/?q=text',
                'expectedIsSearchPage' => true,
                'expectedIsCategoryPage' => false,
            ],
            'Referer is https://example.com/shop/search?q=text' => [
                'sSearch' => 'Yes',
                'sCategory' => null,
                'referer' => 'https://example.com/shop/search?q=text',
                'expectedIsSearchPage' => true,
                'expectedIsCategoryPage' => false,
            ],
        ];
    }

    /**
     * @dataProvider searchPageProvider
     *
     * @param string $sSearch
     * @param string $sCategory
     * @param string $referer
     * @param bool $expectedIsSearchPage
     * @param bool $expectedIsCategoryPage
     */
    public function testSearchPage(
        $sSearch,
        $sCategory,
        $referer,
        $expectedIsSearchPage,
        $expectedIsCategoryPage
    ) {
        $params['sSearch'] = $sSearch;
        $params['sCategory'] = $sCategory;
        $request = new Enlight_Controller_Request_RequestHttp();
        $request->setControllerName('search')
            ->setActionName('index')
            ->setHeader('referer', $referer)
            ->setModuleName('widgets')
            ->setParams($params);

        // Create mocked args for getting Subject and Request
        $args = $this->createMock(Enlight_Event_EventArgs::class);
        $args->method('get')->with('request')->willReturn($request);

        $cache = $this->createMock(Zend_Cache_Core::class);
        $cache->expects($this->never())->method('save');
        $cache->expects($this->once())->method('test');

        $widgets = Shopware()->Container()->get('fin_search_unified.subscriber.widgets');
        $widgets->onWidgetsPreDispatch($args);

        // Check session values after onWidgetsPreDispatch Call
        $isCategoryPage = Shopware()->Session()->isCategoryPage;
        $isSearchPage = Shopware()->Session()->isSearchPage;

        $this->assertEquals(
            $expectedIsSearchPage,
            $isSearchPage,
            sprintf('Expected isSearchPage to be %s', $expectedIsSearchPage ? 'true' : 'false')
        );
        $this->assertEquals(
            $expectedIsCategoryPage,
            $isCategoryPage,
            sprintf('Expected isCategoryPage to be %s', $expectedIsCategoryPage ? 'true' : 'false')
        );
    }

    public function categoryPageProvider()
    {
        return [
            'Referer is https://example.com/freizeit-elektro/?p=1 -> isSearchPage = false, isCategoryPage = true' => [
                'sSearch' => null,
                'sCategory' => 'yes',
                'referer' => 'https://example.com/freizeit-elektro/?p=1',
                'expectedIsSearchPage' => false,
                'expectedIsCategoryPage' => true,
            ],
            'Referer is http://example.com/freizeit-elektro/?p=1 -> isSearchPage = false, isCategoryPage = true' => [
                'sSearch' => null,
                'sCategory' => 'yes',
                'referer' => 'http://example.com/freizeit-elektro/?p=1',
                'expectedIsSearchPage' => false,
                'expectedIsCategoryPage' => true,
            ],
            'Referer is https://example.com/freizeit-elektro?p=1 -> isSearchPage = false, isCategoryPage = true' => [
                'sSearch' => null,
                'sCategory' => 'yes',
                'referer' => 'https://example.com/freizeit-elektro?p=1',
                'expectedIsSearchPage' => false,
                'expectedIsCategoryPage' => true,
            ],
            'Referer is https://example.com/freizeit-elektro -> isSearchPage = false, isCategoryPage = true' => [
                'sSearch' => null,
                'sCategory' => 'yes',
                'referer' => 'https://example.com/freizeit-elektro',
                'expectedIsSearchPage' => false,
                'expectedIsCategoryPage' => true,
            ],
            'Referer is https://example.com/freizeit-elektro/ -> isSearchPage = false, isCategoryPage = true' => [
                'sSearch' => null,
                'sCategory' => 'yes',
                'referer' => 'https://example.com/freizeit-elektro/',
                'expectedIsSearchPage' => false,
                'expectedIsCategoryPage' => true,
            ],
            'Referer is https://example.com/shop/freizeit-elektro/?p=1 -> isSearchPage = false, isCategoryPage = true' => [
                'sSearch' => null,
                'sCategory' => 'yes',
                'referer' => 'https://example.com/shop/freizeit-elektro/?p=1',
                'expectedIsSearchPage' => false,
                'expectedIsCategoryPage' => true,
            ],
            'Referer is https://example.com/shop/freizeit-elektro?p=1 -> isSearchPage = false, isCategoryPage = true' => [
                'sSearch' => null,
                'sCategory' => 'yes',
                'referer' => 'https://example.com/shop/freizeit-elektro?p=1',
                'expectedIsSearchPage' => false,
                'expectedIsCategoryPage' => true,
            ],
            'Referer is https://example.com/i-do-not-exist -> isSearchPage = false, isCategoryPage = false' => [
                'sSearch' => null,
                'sCategory' => 'yes',
                'referer' => 'https://example.com/i-do-not-exist',
                'expectedIsSearchPage' => false,
                'expectedIsCategoryPage' => false,
            ],
            'Referer is https://example.com/i-do-not-exist/ -> isSearchPage = false, isCategoryPage = false' => [
                'sSearch' => null,
                'sCategory' => 'yes',
                'referer' => 'https://example.com/i-do-not-exist/',
                'expectedIsSearchPage' => false,
                'expectedIsCategoryPage' => false,
            ],
        ];
    }

    /**
     * @dataProvider categoryPageProvider
     *
     * @param string $sSearch
     * @param string $sCategory
     * @param string $referer
     * @param bool $expectedIsSearchPage
     * @param bool $expectedIsCategoryPage
     */

    public function testCategoryPage(
        $sSearch,
        $sCategory,
        $referer,
        $expectedIsSearchPage,
        $expectedIsCategoryPage
    ) {
        $params['sSearch'] = $sSearch;
        $params['sCategory'] = $sCategory;
        $request = new Enlight_Controller_Request_RequestHttp();
        $request->setControllerName('listing')
            ->setActionName('listingCount')
            ->setModuleName('widgets')
            ->setHeader('referer', $referer)
            ->setParams($params);

        // Create mocked args for getting Subject and Request
        $args = $this->createMock(Enlight_Controller_ActionEventArgs::class);
        $args->method('get')->with('request')->willReturn($request);

        $cache = $this->createMock(Zend_Cache_Core::class);
        $cache->method('save');
        $cache->method('test');

        $widgets = Shopware()->Container()->get('fin_search_unified.subscriber.widgets');
        $widgets->onWidgetsPreDispatch($args);

        // Check session values after FrontendPreDispatch Call
        $isCategoryPage = Shopware()->Session()->isCategoryPage;
        $isSearchPage = Shopware()->Session()->isSearchPage;

        $this->assertEquals(
            $expectedIsSearchPage,
            $isSearchPage
        );
        $this->assertEquals(
            $expectedIsCategoryPage,
            $isCategoryPage
        );
    }

    public function homePageProvider()
    {
        return [
            'Referer is https://example.com/' => [
                'referer' => 'https://example.com/',
                'isSearchPage' => false,
                'isCategoryPage' => false,
            ],
            'Referer is https://example.com' => [
                'referer' => 'https://example.com',
                'isSearchPage' => false,
                'isCategoryPage' => false,
            ],
            'Referer is https://example.com/shop/' => [
                'referer' => 'https://example.com/shop/',
                'isSearchPage' => false,
                'isCategoryPage' => false,
            ],
            'Referer is https://example.com/shop' => [
                'referer' => 'https://example.com/shop',
                'isSearchPage' => false,
                'isCategoryPage' => false,
            ]
        ];
    }
}
