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
               ->addFilter((new Filter())->setLabel('Robes')->setActive(true));

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
               );

        $this->assertEquals('Price-€-7-9', $this->serializer->serialize([$facet]));
    }

    public function testAddFilterToFacetFilters()
    {
        $filter = (new Filter())
                ->setLabel('Doesn\'t matter')
                ->setActive(true)
                ->setProperty('symbol', '€')
                ->setValue([7, 9]);
        $facet = (new Facet())
               ->setLabel('Price')
               ->addFilter($filter);

        $facetFilters = $this->serializer->addFilterToFacetFilters([], $filter, $facet);
        $this->assertEquals(
            [
                'Price' => [
                    "Doesn't matter" => "Doesn't matter",
                ],
            ],
            $facetFilters
        );
        $facetFilters = $this->serializer->removeFilterFromFacetFilters($facetFilters, $filter, $facet);
        $this->assertEmpty($facetFilters);
    }

    public function testAddFilterToFacetFiltersWithRangeProperty()
    {
        $filter = (new Filter())
                ->setLabel('Doesn\'t matter')
                ->setActive(true)
                ->setProperty('symbol', '€')
                ->setValue([7, 9]);
        $facet = (new Facet())
               ->setLabel('Price')
               ->setProperty('range', true)
                ->setProperty('min', 0)
                ->setProperty('max', 100)
               ->addFilter($filter);

        $facetFilters = $this->serializer->addFilterToFacetFilters([], $filter, $facet);
        $this->assertEquals(
            [
                'Price' => [
                    '€',
                    '0',
                    '100',
                ],
            ],
            $facetFilters
        );
        // Test Remove
        $facetFilters = $this->serializer->removeFilterFromFacetFilters($facetFilters, $filter, $facet);
        $this->assertEmpty($facetFilters);
    }
}
