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
}
