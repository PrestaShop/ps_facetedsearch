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

    public function test_setFiltersFromEncodedFacets_simple_facets()
    {
        $template = [
            (new Facet())
                ->setLabel('Categories')
                ->addFilter((new Filter())->setLabel('Tops')->setActive(false))
                ->addFilter((new Filter())->setLabel('Dresses')->setActive(false)),
            (new Facet())
                ->setLabel('Strange Birds')
                ->addFilter((new Filter())->setLabel('Penguins')->setActive(false))
                ->addFilter((new Filter())->setLabel('Puffins')->setActive(false)),
        ];

        $encodedFacets = 'Categories-Dresses/Strange Birds-Penguins';

        $facets = $this
            ->serializer
            ->setFiltersFromEncodedFacets(
                $template,
                $encodedFacets
            )
        ;

        /*
         * We check that the Dresses filter in the first facet
         * and the Pengins filter in the Strange Birds facet were enabled.
         */

        $this->assertTrue($facets[0]->getFilters()[1]->isActive());
        $this->assertTrue($facets[1]->getFilters()[0]->isActive());
    }

    public function test_setFiltersFromEncodedFacets_range_filter_adds_the_filter()
    {
        $template = [
            (new Facet())->setLabel('Price')->setProperty('range', true),
        ];

        $encodedFacets = 'Price-€-5-3.14';

        $facets = $this
            ->serializer
            ->setFiltersFromEncodedFacets(
                $template,
                $encodedFacets
            )
        ;

        $priceFilter = $facets[0]->getFilters()[0];

        $this->assertEquals([
            'from' => 5,
            'to' => 3.14,
        ], $priceFilter->getValue());

        $this->assertTrue($priceFilter->isActive());
    }

    public function test_setFiltersFromEncodedFacets_range_filter_enables_the_filter()
    {
        $template = [
            (new Facet())->setLabel('Price')->setProperty('range', true)
                ->addFilter(
                    (new Filter())
                        ->setActive(false)
                        ->setValue(['from' => 2, 'to' => 7])
                ),
        ];

        $encodedFacets = 'Price-€-5-3.14';

        $facets = $this
            ->serializer
            ->setFiltersFromEncodedFacets(
                $template,
                $encodedFacets
            )
        ;

        $filters = $facets[0]->getFilters();

        $this->assertCount(1, $filters);

        $priceFilter = $filters[0];

        $this->assertEquals([
            'from' => 2,
            'to' => 7,
        ], $priceFilter->getValue());

        $this->assertTrue($priceFilter->isActive());
    }
}
