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
use Ps_Facetedsearch;
use ReflectionMethod;

class PriceRangeKeyTest extends MockeryTestCase
{
    const FRANCE = 8;
    const GERMANY = 1;

    private $module;
    private $method;

    protected function setUp()
    {
        $this->module = Mockery::mock(Ps_Facetedsearch::class);
        $this->method = new ReflectionMethod(Ps_Facetedsearch::class, 'getPriceRangeKey');
        $this->method->setAccessible(true);
    }

    public function testCountriesShareTheKeyWithoutCountryDependentPrice()
    {
        $specificPrices = [$this->specificPrice(0, false), $this->specificPrice(0, false, 0)];

        $this->assertSame('all', $this->getKey(self::FRANCE, $specificPrices));
        $this->assertSame('all', $this->getKey(self::GERMANY, $specificPrices));
        $this->assertSame('all', $this->getKey(self::FRANCE, []));
    }

    public function testCountryWithItsOwnSpecificPriceHasItsOwnKey()
    {
        $specificPrices = [$this->specificPrice(self::GERMANY, false)];

        $this->assertSame('country-' . self::GERMANY, $this->getKey(self::GERMANY, $specificPrices));
        $this->assertSame('all', $this->getKey(self::FRANCE, $specificPrices));
    }

    public function testGlobalSpecificPriceOfACountryGivesItItsOwnKey()
    {
        $specificPrices = [$this->specificPrice(self::GERMANY, false, 0)];

        $this->assertSame('country-' . self::GERMANY, $this->getKey(self::GERMANY, $specificPrices));
    }

    public function testTaxIncludedAmountReductionGroupsCountriesByTaxRate()
    {
        $specificPrices = [$this->specificPrice(0, true)];
        $rates = [self::FRANCE => 20.0, self::GERMANY => 19.0];
        $getTaxRate = function ($idCountry) use ($rates) {
            return $rates[$idCountry];
        };

        $this->assertSame('rate-20', $this->getKey(self::FRANCE, $specificPrices, false, $getTaxRate));
        $this->assertSame('rate-19', $this->getKey(self::GERMANY, $specificPrices, false, $getTaxRate));
    }

    public function testCountrySpecificPriceWinsOverTheTaxRate()
    {
        $specificPrices = [$this->specificPrice(self::GERMANY, true)];

        $this->assertSame('country-' . self::GERMANY, $this->getKey(self::GERMANY, $specificPrices));
    }

    public function testEveryCountryHasItsOwnKeyWhenAModuleChangesThePriceCalculation()
    {
        $this->assertSame('country-' . self::FRANCE, $this->getKey(self::FRANCE, [], true));
    }

    private function getKey($idCountry, array $specificPrices, $priceCalculationHooked = false, ?callable $getTaxRate = null)
    {
        $getTaxRate = $getTaxRate ?: function () {
            $this->fail('The tax rate is only needed for tax included amount reductions');
        };

        return $this->method->invoke($this->module, $idCountry, $specificPrices, $priceCalculationHooked, $getTaxRate);
    }

    private function specificPrice($idCountry, $taxIncludedReduction, $idProduct = 42)
    {
        // As returned by the database
        return [
            'id_product' => (string) $idProduct,
            'id_country' => (string) $idCountry,
            'tax_included_reduction' => $taxIncludedReduction ? '1' : '0',
        ];
    }
}
