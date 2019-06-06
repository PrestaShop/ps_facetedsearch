<?php

namespace PrestaShop\Module\FacetedSearch\Tests;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PrestaShop\Module\FacetedSearch\URLSerializer;

class URLSerializerTest extends MockeryTestCase
{
    private $serializer;

    protected function setUp()
    {
        $this->serializer = new URLSerializer();
    }

    private function mockFacet($label, $properties = [])
    {
        $facet = Mockery::mock('PrestaShop\PrestaShop\Core\Product\Search\Facet');
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
        $filter = Mockery::mock('PrestaShop\PrestaShop\Core\Product\Search\Filter');
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
}
