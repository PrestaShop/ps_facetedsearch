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
namespace PrestaShop\Module\FacetedSearch\Product\Search;

use PrestaShop\PrestaShop\Core\Product\Search\Filter as CoreFilter;

class Filter extends CoreFilter
{
    /**
     * @var array the filter properties
     */
    private $properties = [];

    /**
     * @var string the filter with encoded facets
     */
    private $encodedFacets = '';

    /**
     * @param string $encodedFacets
     *
     * @return $this
     */
    public function setEncodedFacets($encodedFacets)
    {
        $this->encodedFacets = $encodedFacets;

        return $this;
    }

    /**
     * @return array
     */
    public function getEncodedFacets()
    {
        return $this->encodedFacets;
    }

    /**
     * @return array
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * @param string $name the filter property name
     * @param mixed $value the filter property value
     *
     * @return $this
     */
    public function setProperty($name, $value)
    {
        $this->properties[$name] = $value;

        return $this;
    }

    /**
     * @param string $name the filter property name
     *
     * @return mixed|null
     */
    public function getProperty($name)
    {
        if (!array_key_exists($name, $this->properties)) {
            return null;
        }

        return $this->properties[$name];
    }

    /**
     * @return array an array representation of the filter
     */
    public function toArray()
    {
        return [
            'label' => $this->getLabel(),
            'type' => $this->getType(),
            'active' => $this->isActive(),
            'displayed' => $this->isDisplayed(),
            'properties' => $this->getProperties(),
            'magnitude' => $this->getMagnitude(),
            'value' => $this->getValue(),
            'nextEncodedFacets' => $this->getNextEncodedFacets(),
            'encodedFacets' => $this->getEncodedFacets(),
        ];
    }
}
