<?php

require_once __DIR__ . '/../../src/URLSerializer.php';

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\FacetedSearch\URLSerializer;
use PrestaShop\Module\FacetedSearch\Product\Search\Filter;
use PrestaShop\PrestaShop\Core\Product\Search\Facet;

class URLSerializerTest extends TestCase
{
    private $serializer;

    protected function setup()
    {
        $this->serializer = new URLSerializer();
    }

    public function test_serialize_one_facet()
    {
        $facet = (new Facet())
            ->setLabel('Categories')
            ->addFilter((new Filter())->setLabel('Tops')->setActive(true))
            ->addFilter((new Filter())->setLabel('Robes')->setActive(true))
        ;

        $this->assertEquals('Categories-Tops-Robes', $this->serializer->serialize([$facet]));
    }

    public function test_serialize_price_facet()
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
