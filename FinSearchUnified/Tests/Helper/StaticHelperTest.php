<?php

namespace FinSearchUnified\Tests\Helper;

use FinSearchUnified\Constants;
use FinSearchUnified\Helper\StaticHelper;
use Shopware\Components\Test\Plugin\TestCase;
use Shopware\Models\Category\Category;

class StaticHelperTest extends TestCase
{
    /**
     * Data provider for test cases
     *
     * @return array
     */
    public static function configDataProvider()
    {
        return [
            'FINDOLOGIC is inactive' => [
                'ActivateFindologic' => false,
                'ShopKey' => 'ABCD0815',
                'ActivateFindologicForCategoryPages' => true,
                'findologicDI' => false,
                'isSearchPage' => null,
                'isCategoryPage' => null,
                'expected' => true
            ],
            'Shopkey is empty' => [
                'ActivateFindologic' => true,
                'ShopKey' => '',
                'ActivateFindologicForCategoryPages' => true,
                'findologicDI' => false,
                'isSearchPage' => null,
                'isCategoryPage' => null,
                'expected' => true
            ],
            "Shopkey is 'Findologic ShopKey'" => [
                'ActivateFindologic' => true,
                'ShopKey' => 'Findologic ShopKey',
                'ActivateFindologicForCategoryPages' => true,
                'findologicDI' => false,
                'isSearchPage' => null,
                'isCategoryPage' => null,
                'expected' => true
            ],
            'FINDOLOGIC is active but integration type is DI' => [
                'ActivateFindologic' => true,
                'ShopKey' => 'ABCD0815',
                'ActivateFindologicForCategoryPages' => true,
                'findologicDI' => true,
                'isSearchPage' => null,
                'isCategoryPage' => null,
                'expected' => true
            ],
            'FINDOLOGIC is active but the current page is neither the search nor a category page' => [
                'ActivateFindologic' => true,
                'ShopKey' => 'ABCD0815',
                'ActivateFindologicForCategoryPages' => true,
                'findologicDI' => false,
                'isSearchPage' => false,
                'isCategoryPage' => false,
                'expected' => true
            ],
            'FINDOLOGIC is not active on category pages' => [
                'ActivateFindologic' => true,
                'ShopKey' => 'ABCD0815',
                'ActivateFindologicForCategoryPages' => false,
                'findologicDI' => false,
                'isSearchPage' => false,
                'isCategoryPage' => true,
                'expected' => true
            ],
            'FINDOLOGIC is active in search' => [
                'ActivateFindologic' => true,
                'ShopKey' => 'ABCD0815',
                'ActivateFindologicForCategoryPages' => false,
                'findologicDI' => false,
                'isSearchPage' => true,
                'isCategoryPage' => false,
                'expected' => false
            ],
            'FINDOLOGIC is active on category pages' => [
                'ActivateFindologic' => true,
                'ShopKey' => 'ABCD0815',
                'ActivateFindologicForCategoryPages' => true,
                'findologicDI' => false,
                'isSearchPage' => false,
                'isCategoryPage' => true,
                'expected' => false
            ]
        ];
    }

    /**
     * Data provider for testing removal of control characters
     *
     * @return array
     */
    public static function controlCharacterProvider()
    {
        return [
            'Strings with only letters and numbers' => [
                'Findologic123',
                'Findologic123',
                'Expected string to return unchanged'
            ],
            'String with control characters' => [
                "Findologic\n1\t2\r3",
                'Findologic123',
                'Expected control characters to be stripped way'
            ],
            'String with another set of control characters' => [
                "Findologic\xC2\x9F\xC2\x80 Rocks",
                'Findologic Rocks',
                'Expected control characters to be stripped way'
            ],
            'String with special characters' => [
                'Findologic&123',
                'Findologic&123',
                'Expected special characters to be returned as they are'
            ],
            'String with umlauts' => [
                'Findolögic123',
                'Findolögic123',
                'Expected umlauts to be left unaltered.'
            ]
        ];
    }

    /**
     * Data provider for testing cleanString method
     *
     * @return array
     */
    public static function cleanStringProvider()
    {
        return [
            'String with HTML tags' => [
                "<span>Findologic Rocks</span>",
                'Findologic Rocks',
                'Expected HTML tags to be stripped away'
            ],
            'String with single quotes' => [
                "Findologic's team rocks",
                'Findologic\'s team rocks',
                'Expected single quotes to be escaped with back slash'
            ],
            'String with double quotes' => [
                'Findologic "Rocks!"',
                "Findologic \"Rocks!\"",
                'Expected double quotes to be escaped with back slash'
            ],
            'String with back slashes' => [
                "Findologic\ Rocks!\\",
                'Findologic Rocks!',
                'Expected back slashes to be stripped away'
            ],
            'String with preceding space' => [
                ' Findologic Rocks ',
                'Findologic Rocks',
                'Expected preceding and succeeding spaces to be stripped away'
            ],
            'Strings with only letters and numbers' => [
                'Findologic123',
                'Findologic123',
                'Expected string to return unchanged'
            ],
            'String with control characters' => [
                "Findologic\n1\t2\r3",
                'Findologic 1 2 3',
                'Expected control characters to be stripped way'
            ],
            'String with another set of control characters' => [
                "Findologic\xC2\x9F\xC2\x80 Rocks",
                'Findologic Rocks',
                'Expected control characters to be stripped way'
            ],
            'String with special characters' => [
                'Findologic&123!',
                'Findologic&123!',
                'Expected special characters to be returned as they are'
            ],
            'String with umlauts' => [
                'Findolögic123',
                'Findolögic123',
                'Expected umlauts to be left unaltered.'
            ]
        ];
    }

    /**
     * Data provider for testing category names
     *
     * @return array
     */
    public function categoryNamesProvider()
    {
        return [
            'Root category name without children' => [1, ' Root ', 'Root'],
            'Category name with parent' => [12, ' Tees ', 'Genusswelten_Tees%20und%20Zubeh%C3%B6r_Tees'],
        ];
    }

    /**
     * @dataProvider configDataProvider
     *
     * @param bool $isActive
     * @param string $shopKey
     * @param bool $isActiveForCategory
     * @param bool $checkIntegration
     * @param bool $isSearchPage
     * @param bool $isCategoryPage
     * @param bool $expectedResult
     */
    public function testUseShopSearch(
        $isActive,
        $shopKey,
        $isActiveForCategory,
        $checkIntegration,
        $isSearchPage,
        $isCategoryPage,
        $expectedResult
    ) {
        $configArray = [
            ['ActivateFindologic', $isActive],
            ['ShopKey', $shopKey],
            ['ActivateFindologicForCategoryPages', $isActiveForCategory],
            ['IntegrationType', $checkIntegration ? Constants::INTEGRATION_TYPE_DI : Constants::INTEGRATION_TYPE_API]
        ];

        if ($isSearchPage !== null) {
            $sessionArray = [
                ['isSearchPage', $isSearchPage],
                ['isCategoryPage', $isCategoryPage],
                ['findologicDI', $checkIntegration]
            ];

            // Create Mock object for Shopware Session
            $session = $this->getMockBuilder('\Enlight_Components_Session_Namespace')
                ->setMethods(['offsetGet'])
                ->getMock();
            $session->expects($this->atLeastOnce())
                ->method('offsetGet')
                ->willReturnMap($sessionArray);

            // Assign mocked session variable to application container
            Shopware()->Container()->set('session', $session);
        }
        // Create Mock object for Shopware Config
        $config = $this->getMockBuilder('\Shopware_Components_Config')
            ->setMethods(['offsetGet'])
            ->disableOriginalConstructor()
            ->getMock();
        $config->expects($this->atLeastOnce())
            ->method('offsetGet')
            ->willReturnMap($configArray);

        // Assign mocked config variable to application container
        Shopware()->Container()->set('config', $config);

        $result = StaticHelper::useShopSearch();
        $error = 'Expected %s search to be triggered but it was not';
        $shop = $expectedResult ? 'shop' : 'FINDOLOGIC';
        $this->assertEquals($result, $expectedResult, sprintf($error, $shop));
    }

    /**
     * @dataProvider controlCharacterProvider
     *
     * @param string $text
     * @param string $expected
     * @param string $errorMessage
     */
    public function testControlCharacterMethod($text, $expected, $errorMessage)
    {
        $result = StaticHelper::removeControlCharacters($text);
        $this->assertEquals($expected, $result, $errorMessage);
    }

    /**
     * @dataProvider cleanStringProvider
     *
     * @param string $text
     * @param string $expected
     * @param string $errorMessage
     */
    public function testCleanStringMethod($text, $expected, $errorMessage)
    {
        $result = StaticHelper::cleanString($text);
        $this->assertEquals($expected, $result, $errorMessage);
    }

    /**
     * @dataProvider categoryNamesProvider
     *
     * @param int $categoryId
     * @param string $category
     * @param string $expected
     *
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function testBuildCategoryName($categoryId, $category, $expected)
    {
        $categoryModel = Shopware()->Models()->getRepository('Shopware\Models\Category\Category')
            ->find($categoryId);
        $this->assertInstanceOf('Shopware\Models\Category\Category', $categoryModel);

        // Set category name with preceeding and succeeding spaces
        $categoryModel->setName($category);
        $parent = $categoryModel->getParent();

        // Set parent category name with preceeding and succeeding spaces
        if ($parent !== null) {
            $this->wrapParentsName($parent, $categoryModel);
        }
        // Persist changes to database
        Shopware()->Models()->flush();
        $result = StaticHelper::buildCategoryName($categoryModel->getId());

        $this->assertSame($expected, $result, "Expected category name to be trimmed but was not");
    }

    /**
     * Helper method to add spaces to category name recursively
     *
     * @param Category $parent
     * @param Category $categoryModel
     */
    private function wrapParentsName($parent, $categoryModel)
    {
        // Trim name here so it does not keep adding spaces on every run
        $parentName = trim($parent->getName());
        $parentName = str_pad($parentName, strlen($parentName) + 2, ' ', STR_PAD_BOTH);

        $parent->setName($parentName);
        Shopware()->Models()->persist($parent);

        $categoryModel->setParent($parent);
        Shopware()->Models()->persist($categoryModel);

        if ($parent->getParent() !== null) {
            $this->wrapParentsName($parent->getParent(), $parent);
        }
    }
}