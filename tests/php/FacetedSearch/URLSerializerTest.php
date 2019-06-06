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

    private function mockFacet($label)
    {
        $facet = Mockery::mock('PrestaShop\PrestaShop\Core\Product\Search\Facet');
        $facet->shouldReceive('getLabel')
            ->andReturn($label);

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

    public function testSerializeOneFacet()
    {
        $first = $this->mockFilter('Tops', true);
        $second = $this->mockFilter('Robes', true);

        $facet = $this->mockFacet('Categories');
        $facet->shouldReceive('getFilters')
            ->andReturn([$first, $second]);

        $this->assertEquals('Categories-Tops-Robes', $this->serializer->serialize([$facet]));
    }

    public function testSerializePriceFacet()
    {
        $facet = (new Facet())
            ->setLabel('Price')
            ->setProperty('range', true)
            ->addFilter(
                (new Filter())
                ->setLabel('Doesn\'t matter')
                ->setActive(true)
                ->setProperty('symbol', '€')
                ->setValue([7, 9])
            )
        ;

        $this->assertEquals('Price-€-7-9', $this->serializer->serialize([$facet]));
    }
}
