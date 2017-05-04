<?php
/**
 * 2007-2017 PrestaShop
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
 * @copyright 2007-2017 PrestaShop SA
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */


class Ps_FacetedsearchRangeAggregator
{
    private function makeNode(array $range, $minColumnIndex, $maxColumnIndex)
    {
        $min = $range[$minColumnIndex];
        $max = $range[$maxColumnIndex];
        if ($min === $max) {
            $min = $range[$minColumnIndex] > 0 ? $range[$minColumnIndex] - 1 : 0;
            $max = $max + 1;
        }

        return [
            'min' => $min,
            'max' => $max,
            'count' => 1,
            'left' => null,
            'right' => null,
        ];
    }

    private function addNode(array &$target, array $node)
    {
        if ($node['min'] > $target['max']) {
            if ($target['right']) {
                $this->addNode($target['right'], $node);
            } else {
                $target['right'] = $node;
            }
        } elseif ($node['max'] < $target['min']) {
            if ($target['left']) {
                $this->addNode($target['left'], $node);
            } else {
                $target['left'] = $node;
            }
        } elseif ($node['max'] <= $target['max'] && $node['min'] >= $target['min']) {
            ++$target['count'];
        } else {
            $newMin = min($node['min'], $target['min']);
            $newMax = max($node['max'], $target['max']);
            $target['count'] += $node['count'];

            if ($target['left']) {
                if ($target['left']['min'] >= $newMin) {
                    $target['count'] += $target['left']['count'];
                    $target['left'] = null;
                }
            }

            if ($target['right']) {
                if ($target['right']['max'] <= $newMax) {
                    $target['count'] += $target['right']['count'];
                    $target['right'] = null;
                }
            }

            $target['min'] = $newMin;
            $target['max'] = $newMax;
        }
    }

    public function aggregateRanges(array $ranges, $minColumnIndex, $maxColumnIndex)
    {
        $rootNode = null;

        foreach ($ranges as $range) {
            $node = $this->makeNode($range, $minColumnIndex, $maxColumnIndex);
            if (null === $rootNode) {
                $rootNode = $node;
            } else {
                $this->addNode($rootNode, $node);
            }
        }

        $flat = $this->flatten($rootNode);

        return $flat;
    }

    private function flatten(array $node)
    {
        $min = $node['min'];
        $max = $node['max'];

        $ranges = [[
            'min' => $min,
            'max' => $max,
            'count' => $node['count'],
        ]];

        if ($node['left']) {
            $flatLeft = $this->flatten($node['left']);
            $min = $flatLeft['min'];
            $ranges = array_merge($flatLeft['ranges'], $ranges);
        }

        if ($node['right']) {
            $flatRight = $this->flatten($node['right']);
            $max = $flatRight['max'];
            $ranges = array_merge($ranges, $flatRight['ranges']);
        }

        return [
            'min' => $min,
            'max' => $max,
            'ranges' => $ranges,
        ];
    }

    public function getRangesFromList(array $list, $valueColumnIndex)
    {
        $min = null;
        $max = null;

        $byValue = [];
        foreach ($list as $item) {
            $n = $item[$valueColumnIndex];
            if ($min === null || $n < $min) {
                $min = $n;
            }
            if ($max === null || $n > $max) {
                $max = $n;
            }

            $key = "n$n";
            if (!array_key_exists($key, $byValue)) {
                $byValue[$key] = [
                    'count' => 0,
                    'value' => $n,
                ];
            }
            ++$byValue[$key]['count'];
        }

        $ranges = [];
        $lastValue = null;
        $lastCount = 0;

        usort($byValue, function (array $a, array $b) {
            return $a['value'] > $b['value'] ? 1 : -1;
        });

        foreach ($byValue as $countAndValue) {
            $value = $countAndValue['value'];
            $count = $countAndValue['count'];
            if ($lastValue !== null) {
                $ranges[] = [
                    'min' => $lastValue,
                    'max' => $value,
                    'count' => $count + $lastCount,
                ];
            } else {
                $lastCount = $count;
            }
            $lastValue = $value;
            $lastCount = $count;
        }

        return [
            'min' => $min,
            'max' => $max,
            'ranges' => $ranges,
        ];
    }

    public function mergeRanges(array $ranges, $outputLength)
    {
        if ($outputLength >= count($ranges)) {
            $raw_ranges = $ranges;
        } else {
            $parts = array_chunk($ranges, floor(count($ranges) / $outputLength));

            $raw_ranges = array_map(function (array $ranges) {
                $min = $ranges[0]['min'];
                $max = $ranges[count($ranges) - 1]['max'];

                return [
                    'min' => $min,
                    'max' => $max,
                    'count' => array_reduce($ranges, function ($count, array $range) {
                        return $count + $range['count'];
                    }, 0),
                ];
            }, $parts);
        }

        return $raw_ranges;
    }
}
