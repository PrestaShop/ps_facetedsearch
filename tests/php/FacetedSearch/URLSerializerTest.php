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

namespace PrestaShop\Module\FacetedSearch\Tests;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PrestaShop\Module\FacetedSearch\URLSerializer;
use PrestaShop\PrestaShop\Core\Product\Search\Facet;
use PrestaShop\PrestaShop\Core\Product\Search\Filter;

class URLSerializerTest extends MockeryTestCase
{
    private $serializer;

    protected function setUp()
    {
        $this->serializer = new URLSerializer();
    }

    private function mockFacet($label, $properties = [])
    {
        $facet = Mockery::mock(Facet::class);
        $facet->shouldReceive('getLabel')
            ->andReturn($label);

        $facet->shouldReceive('getProperty')
            ->andReturnUsing(
                function ($arg) use ($properties) {
                    return isset($properties[$arg]) ? $properties[$arg] : null;
                }
            );

        return $facet;
    }

    private function mockFilter($label, $active = false, $value = null, $properties = [])
    {
        $filter = Mockery::mock(Filter::class);
        $filter->shouldReceive('getLabel')
            ->andReturn($label);
        $filter->shouldReceive('isActive')
            ->andReturn($active);

        if ($value !== null) {
            $filter->shouldReceive('getValue')
                ->andReturn($value);
        }

        $filter->shouldReceive('getProperty')
            ->andReturnUsing(
                function ($arg) use ($properties) {
                    return isset($properties[$arg]) ? $properties[$arg] : null;
                }
            );

        return $filter;
    }

    public function testGetActiveFilters()
    {
        $first = $this->mockFilter('Tops', true);
        $second = $this->mockFilter('Robes', false);

        $facet = $this->mockFacet('Categories', ['range' => false]);
        $facet->shouldReceive('getFilters')
            ->andReturn([$first, $second]);

        $this->assertEquals(
            ['Categories' => ['Tops' => 'Tops']],
            $this->serializer->getActiveFacetFiltersFromFacets([$facet])
        );
    }

    public function testGetActiveFiltersWithRange()
    {
        $filter = $this->mockFilter('filter', true, [0, 100], ['symbol' => '$']);
        $facet = $this->mockFacet('Price', ['range' => true]);
        $facet->shouldReceive('getFilters')
            ->andReturn([$filter]);

        $this->assertEquals(
            ['Price' => ['$', 0, 100]],
            $this->serializer->getActiveFacetFiltersFromFacets([$facet])
        );
    }

    public function testAddAndRemoveFiltersWithoutRange()
    {
        $filter = $this->mockFilter('Tops');
        $facet = $this->mockFacet('Categories', ['range' => false]);
        $facetsFilters = $this->serializer->addFilterToFacetFilters(
            [],
            $filter,
            $facet
        );
        $this->assertEquals(
            ['Categories' => ['Tops' => 'Tops']],
            $facetsFilters
        );
        $facetsFilters = $this->serializer->removeFilterFromFacetFilters(
            $facetsFilters,
            $filter,
            $facet
        );
        $this->assertEquals(
            [],
            $facetsFilters
        );
    }

    public function testAddAndRemoveFiltersWithRangeAndMinMax()
    {
        $filter = $this->mockFilter(
            'filter',
            true,
            [0, 100],
            ['symbol' => '$']
        );
        $facet = $this->mockFacet(
            'Price',
            [
                'range' => true,
                'values' => [],
                'min' => 0,
                'max' => 200,
            ]
        );
        $facetsFilters = $this->serializer->addFilterToFacetFilters(
            [],
            $filter,
            $facet
        );
        $this->assertEquals(
            ['Price' => ['$', 0, 200]],
            $facetsFilters
        );
        $facetsFilters = $this->serializer->removeFilterFromFacetFilters(
            $facetsFilters,
            $filter,
            $facet
        );
        $this->assertEquals(
            [],
            $facetsFilters
        );
    }

    public function testAddAndRemoveFiltersWithRange()
    {
        $filter = $this->mockFilter(
            'filter',
            true,
            [0, 100],
            ['symbol' => '$']
        );
        $facet = $this->mockFacet(
            'Price',
            [
                'range' => true,
                'values' => [10, 100],
            ]
        );
        $facetsFilters = $this->serializer->addFilterToFacetFilters(
            [],
            $filter,
            $facet
        );
        $this->assertEquals(
            ['Price' => ['$', 10, 100]],
            $facetsFilters
        );
        $facetsFilters = $this->serializer->removeFilterFromFacetFilters(
            $facetsFilters,
            $filter,
            $facet
        );
        $this->assertEquals(
            [],
            $facetsFilters
        );
    }

    /**
     * @dataProvider getSerializerData
     */
    public function testSerializeUnserialize($expected, array $fragment)
    {
        $this->assertEquals($expected, $this->serializer->serialize($fragment));
        $this->assertEquals($fragment, $this->serializer->unserialize($expected));
    }

    public function getSerializerData()
    {
        return [
            [
                'a-b',
                ['a' => ['b']],
            ],
            [
                'a-b-c',
                ['a' => ['b', 'c']],
            ],
            [
                'a-b-c/x-y-z',
                ['a' => ['b', 'c'], 'x' => ['y', 'z']],
            ],
            [
                'a-b\-c',
                ['a' => ['b-c']],
            ],
            [
                'Category-Men \/ Women \ \-Children-Clothes/Size-\-1-2',
                ['Category' => ['Men / Women \ -Children', 'Clothes'], 'Size' => ['-1', '2']],
            ],
            [
                'Category-\-\-\--\-2/\-Size\--\-1-\-2\-',
                ['Category' => ['---', '-2'], '-Size-' => ['-1', '-2-']],
            ],
        ];
    }
}
