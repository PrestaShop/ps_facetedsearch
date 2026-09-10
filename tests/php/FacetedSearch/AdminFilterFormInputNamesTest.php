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

use PHPUnit\Framework\TestCase;

/**
 * Ps_Facetedsearch::getContent() decides which filters a template keeps by looking at which
 * layered_selection_* keys are PRESENT in $_POST, never at their value. A checkbox that is off
 * submits nothing, which is what makes that work - so any other input sharing a checkbox's name
 * pins that filter permanently on, and no value can express "off".
 */
class AdminFilterFormInputNamesTest extends TestCase
{
    /**
     * @var string[]
     */
    private $templates = [
        'add.tpl',
        'view.tpl',
    ];

    public function testNoInputShadowsAFilterCheckbox()
    {
        foreach ($this->templates as $template) {
            $markup = file_get_contents(__DIR__ . '/../../../views/templates/admin/' . $template);
            $this->assertNotFalse($markup, sprintf('Could not read %s', $template));

            $checkboxes = $this->inputNamesOfType($markup, 'checkbox');
            // Without this the intersection below is trivially empty whenever the markup changes
            // shape enough to stop matching, and the test would pass while checking nothing.
            $this->assertNotEmpty(
                $checkboxes,
                sprintf('No checkbox found in %s - the parsing needs updating.', $template)
            );

            $shadowed = array_values(array_intersect(
                $checkboxes,
                $this->inputNamesExcludingType($markup, 'checkbox')
            ));

            $this->assertSame(
                [],
                $shadowed,
                sprintf(
                    'In %s these names belong to a filter checkbox and to another input as well, so the '
                    . 'filter is always submitted and can never be turned off: %s',
                    $template,
                    implode(', ', $shadowed)
                )
            );
        }
    }

    /**
     * @param string $markup
     * @param string $type
     *
     * @return string[]
     */
    private function inputNamesOfType($markup, $type)
    {
        return $this->collectNames($markup, $type, true);
    }

    /**
     * @param string $markup
     * @param string $type
     *
     * @return string[]
     */
    private function inputNamesExcludingType($markup, $type)
    {
        return $this->collectNames($markup, $type, false);
    }

    /**
     * Attribute order differs between the hand-written rows and the generated ones, so `name` and
     * `type` are read independently of each other rather than with one combined pattern.
     *
     * @param string $markup
     * @param string $type
     * @param bool $keepMatchingType
     *
     * @return string[]
     */
    private function collectNames($markup, $type, $keepMatchingType)
    {
        if (!preg_match_all('#<input\b[^>]*>#i', $markup, $tags)) {
            return [];
        }

        $names = [];
        foreach ($tags[0] as $tag) {
            if (!preg_match('#\bname="([^"]+)"#i', $tag, $name)) {
                continue;
            }
            preg_match('#\btype="([^"]+)"#i', $tag, $found);
            $isType = isset($found[1]) && strtolower($found[1]) === $type;
            if ($isType === $keepMatchingType) {
                $names[] = $name[1];
            }
        }

        return array_values(array_unique($names));
    }
}
