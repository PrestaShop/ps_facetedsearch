<?php

namespace PrestaShop\Module\FacetedSearch\Tests;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\FacetedSearch\URLSerializer;
use PrestaShop\PrestaShop\Core\Product\Search\Filter;
use PrestaShop\PrestaShop\Core\Product\Search\Facet;

class URLSerializerTest extends TestCase
{
    private $serializer;

    protected function setUp()
    {
        $this->serializer = new URLSerializer();
    }

    public function testSerializeOneFacet()
    {
        $facet = (new Facet())
            ->setLabel('Categories')
            ->addFilter((new Filter())->setLabel('Tops')->setActive(true))
            ->addFilter((new Filter())->setLabel('Robes')->setActive(true))
        ;

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
