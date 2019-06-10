<?php

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
                    return $properties[$arg];
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
                    return $properties[$arg];
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
}
