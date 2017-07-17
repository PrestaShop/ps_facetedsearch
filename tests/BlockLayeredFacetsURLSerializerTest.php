<?php

require_once implode(DIRECTORY_SEPARATOR, [
    __DIR__,
    '..', 'src', 'Ps_FacetedsearchFacetsURLSerializer.php',
]);

use PrestaShop\PrestaShop\Core\Product\Search\Facet;
use PrestaShop\PrestaShop\Core\Product\Search\Filter;

class Ps_FacetedsearchFacetsURLSerializerTest extends PHPUnit_Framework_TestCase
{
    private $serializer;

    public function setup()
    {
        $this->serializer = new Ps_FacetedsearchFacetsURLSerializer();
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
                    ->setValue(['from' => 7, 'to' => 9])
            )
        ;

        $this->assertEquals('Price-€-7-9', $this->serializer->serialize([$facet]));
    }
}
