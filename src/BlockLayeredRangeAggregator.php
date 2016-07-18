<?php

class BlockLayeredRangeAggregator
{
    private function makeNode(array $range, $minColumnIndex, $maxColumnIndex)
    {
        return [
            'min' => $range[$minColumnIndex],
            'max' => $range[$maxColumnIndex],
            'count' => 1,
            'left'  => null,
            'right' => null
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
        } else if ($node['max'] < $target['min']) {
            if ($target['left']) {
                $this->addNode($target['left'], $node);
            } else {
                $target['left'] = $node;
            }
        } else if ($node['max'] <= $target['max'] && $node['min'] >= $target['min']) {
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

    private function flatten (array $node)
    {
        $min = $node['min'];
        $max = $node['max'];

        $ranges = [[
            'min' => $min,
            'max' => $max,
            'count' => $node['count']
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
            'ranges' => $ranges
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
                    'value' => $n
                ];
            }
            ++$byValue[$key]['count'];
        }

        $ranges     = [];
        $lastValue  = null;
        $lastCount  = 0;

        usort($byValue, function (array $a, array $b) {
            return $a['value'] > $b['value'] ? 1 : - 1;
        });

        foreach ($byValue as $countAndValue) {
            $value = $countAndValue['value'];
            $count = $countAndValue['count'];
            if ($lastValue !== null) {
                $ranges[] = [
                    'min'   => $lastValue,
                    'max'   => $value,
                    'count' => $count + $lastCount
                ];
            } else {
                $lastCount = $count;
            }
            $lastValue = $value;
            $lastCount = $count;
        }

        return [
            'min'    => $min,
            'max'    => $max,
            'ranges' => $ranges
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
                    'min'   => $min,
                    'max'   => $max,
                    'count' => array_reduce($ranges, function ($count, array $range) {
                        return $count + $range['count'];
                    }, 0)
                ];
            }, $parts);
        }

        $min = null;
        return array_map(function (array $range) use (&$min) {
            $scale = pow(10, floor(log($range['max'] - $range['min'], 10)));

            if (0 === $scale) {
                $scale = 1;
            }

            if (null !== $min) {
                $range['min'] = $min;
            } else {
                $range['min'] = $scale * floor($range['min'] / $scale);
            }

            $range['max'] = $scale * ceil($range['max'] / $scale);

            $min = $range['max'];
            return $range;
        }, $raw_ranges);
    }
}
