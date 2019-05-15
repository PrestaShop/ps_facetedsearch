<?php
/**
 * 2007-2019 PrestaShop.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2019 PrestaShop SA
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

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
