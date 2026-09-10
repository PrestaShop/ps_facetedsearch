<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace PrestaShop\Module\FacetedSearch\Tests\Filters;

use Combination;
use Configuration;
use Db;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PrestaShop\Module\FacetedSearch\Filters\DataAccessor;
use Shop;

class DataAccessorTest extends MockeryTestCase
{
    private const ID_LANG = 2;

    /**
     * @var DataAccessor
     */
    private $dataAccessor;

    /**
     * @var string Last query handed to the database
     */
    private $query;

    protected function setUp()
    {
        $combinationMock = Mockery::mock(Combination::class);
        $combinationMock->shouldReceive('isFeatureActive')->andReturn(true);
        Combination::setStaticExpectations($combinationMock);

        $shopMock = Mockery::mock(Shop::class);
        $shopMock->shouldReceive('addSqlAssociation')->andReturn('');
        Shop::setStaticExpectations($shopMock);

        $configurationMock = Mockery::mock(Configuration::class);
        $configurationMock->shouldReceive('get')->andReturnUsing(function ($key) {
            $valueMap = [
                'PS_LAYERED_FILTER_FEATURE_VALUES_USE_POSITION' => 0,
            ];

            return isset($valueMap[$key]) ? $valueMap[$key] : null;
        });
        Configuration::setStaticExpectations($configurationMock);

        $this->query = '';
        $dbMock = Mockery::mock(Db::class);
        $dbMock->shouldReceive('executeS')
            ->andReturnUsing(function ($query) {
                $this->query = $query;

                return [];
            });

        $this->dataAccessor = new DataAccessor($dbMock);
    }

    /**
     * The url_name shown in filter URLs lives in the layered_indexable_*_lang_value tables, so the
     * language condition of that join has to be applied to the lang value table itself. Applying it
     * to the neighbouring *_lang table instead leaves the join unrestricted, and the row kept by the
     * GROUP BY is then whichever language the server happens to return - the shop shows the browsed
     * language's name next to another language's URL.
     *
     * @dataProvider getLangValueJoins
     *
     * @param string $method
     * @param array $arguments
     * @param string $table
     * @param string $alias
     */
    public function testLangValueJoinIsRestrictedOnItsOwnTable($method, array $arguments, $table, $alias)
    {
        call_user_func_array([$this->dataAccessor, $method], $arguments);

        $joinCondition = $this->extractJoinCondition($this->query, $table, $alias);

        $this->assertContains(
            $alias . '.`id_lang` = ' . self::ID_LANG,
            $joinCondition,
            sprintf('The %s join must be restricted on %s.id_lang.', $table, $alias)
        );
    }

    /**
     * @dataProvider getLangValueJoins
     *
     * @param string $method
     * @param array $arguments
     * @param string $table
     * @param string $alias
     */
    public function testLangValueJoinDoesNotBorrowAnotherTablesLangColumn($method, array $arguments, $table, $alias)
    {
        call_user_func_array([$this->dataAccessor, $method], $arguments);

        $joinCondition = $this->extractJoinCondition($this->query, $table, $alias);

        preg_match_all('/(\w+)\.`id_lang`/', $joinCondition, $matches);

        $this->assertSame(
            [$alias],
            array_unique($matches[1]),
            sprintf('Only %s.id_lang may appear in the %s join condition.', $alias, $table)
        );
    }

    public function getLangValueJoins()
    {
        return [
            'attributes' => [
                'getAttributes',
                [self::ID_LANG, 1],
                'layered_indexable_attribute_lang_value',
                'lialv',
            ],
            'attribute groups' => [
                'getAttributesGroups',
                [self::ID_LANG],
                'layered_indexable_attribute_group_lang_value',
                'liaglv',
            ],
            'features' => [
                'getFeatures',
                [self::ID_LANG],
                'layered_indexable_feature_lang_value',
                'liflv',
            ],
            'feature values' => [
                'getFeatureValues',
                [1, self::ID_LANG],
                'layered_indexable_feature_value_lang_value',
                'lifvlv',
            ],
        ];
    }

    /**
     * Returns the ON (...) condition of the join introducing $alias.
     *
     * @param string $query
     * @param string $table
     * @param string $alias
     *
     * @return string
     */
    private function extractJoinCondition($query, $table, $alias)
    {
        $pattern = '/' . preg_quote(_DB_PREFIX_ . $table, '/') . '`?\s*(?:AS\s+)?' . preg_quote($alias, '/') . '\s*ON\s*\((?P<condition>[^)]*)\)/i';

        $this->assertRegExp($pattern, $query, sprintf('Expected a join on %s aliased as %s.', $table, $alias));
        preg_match($pattern, $query, $matches);

        return $matches['condition'];
    }
}
