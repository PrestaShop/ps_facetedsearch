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

namespace PrestaShop\Module\FacetedSearch\Filters;

final class NumericFeatureValueParser
{
    /**
     * Extract exactly one non-negative number from a feature value.
     *
     * @param mixed $featureValue
     *
     * @return array|null
     */
    public static function parse($featureValue)
    {
        // Find integer or decimal tokens and reject values containing multiple numbers.
        $featureValue = trim((string) $featureValue);
        preg_match_all('~\d+(?:[.,]\d+)?~u', $featureValue, $matches, PREG_OFFSET_CAPTURE);
        if (count($matches[0]) !== 1) {
            return null;
        }

        // Reject a plus or minus sign immediately before the number, including intervening whitespace.
        $numericToken = $matches[0][0][0];
        $numericTokenOffset = $matches[0][0][1];
        if (preg_match('~[+\-]\s*$~u', substr($featureValue, 0, $numericTokenOffset))) {
            return null;
        }

        // Normalize decimal commas and retain the source precision for the slider step.
        $normalizedValue = str_replace(',', '.', $numericToken);
        $precision = strpos($normalizedValue, '.') === false ? 0 : strlen(substr(strrchr($normalizedValue, '.'), 1));
        $numericValue = (float) $normalizedValue;
        if (!is_finite($numericValue)) {
            return null;
        }

        return [
            'value' => $numericValue,
            'precision' => $precision,
        ];
    }

    /**
     * Validate and normalize a range received from the facet URL.
     *
     * @param mixed $from
     * @param mixed $to
     *
     * @return array|null
     */
    public static function parseRange($from, $to)
    {
        // Accept only complete unsigned numeric bounds.
        if (!preg_match('~^\d+(?:[.,]\d+)?$~', (string) $from) || !preg_match('~^\d+(?:[.,]\d+)?$~', (string) $to)) {
            return null;
        }

        // Normalize decimal commas before comparing the numeric bounds.
        $normalizedFrom = (float) str_replace(',', '.', (string) $from);
        $normalizedTo = (float) str_replace(',', '.', (string) $to);

        // Reject non-finite and reversed ranges before they reach feature ID matching.
        if (!is_finite($normalizedFrom) || !is_finite($normalizedTo) || $normalizedFrom > $normalizedTo) {
            return null;
        }

        return [$normalizedFrom, $normalizedTo];
    }
}
