<?php

class Ps_FacetedsearchRangeAggregator
{
    private function makeNode(array $range, $minColumnIndex, $maxColumnIndex)
    {
        $min = $range[$minColumnIndex];
        $max = $range[$maxColumnIndex];

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

    private function mergeTwoRanges($range1, $range2)
    {
        return [
            'min' => $range1['min'],
            'max' => $range2['max'],
            'count' => $range1['count'] + $range2['count']
        ];
    }

    /*
     * This function goes through all ranges and checks if there's
     * a range with an equal min and max. If this is the case the
     * range will be merged with it's successor (if it's the last
     * range it will be merged with it's predecessor).
     */
    private function cleanupForEqualRanges(array $ranges) {
        $result = $ranges;
        if (count($result) > 1) {
            $pos = $this->findEqualRange($result);
            while (($pos != -1) && (count($result) > 1)) {
                $result = $this->combineRange($result, $pos);
                $pos    = $this->findEqualRange($result);
            }
        }
        return $result;
    }

    /**
     * Recreates the supplied list of ranges while merging
     * the record at position $idx with it's successor 
     * (or predecessor).
     */
    private function combineRange(array $ranges, $idx)
    {
        $idx1 = $idx;
        $idx2 = $idx + 1;
        if ($idx == count($ranges) - 1) {
            // it's the last entry, so we're using the predecessor for the merge (we know there's at least two entries)
            $idx1 = $idx - 1;
            $idx2 = $idx;
        }
        $before = array();
        if ($idx1 > 0) {
            $before = array_slice($ranges, 0, $idx1);
        }
        $merged = $this->mergeTwoRanges($ranges[$idx1], $ranges[$idx2]);
        $after = array();
        if ($idx2 + 1 < count($ranges)) {
            $after = array_slice($ranges, $idx2 + 1);
        }
        $result = $before;
        array_push($result, $merged);
        $result = array_merge($result, $after);
        return $result;
    }

    /*
     * Returns -1 in case there's no range with an equal min and max.
     * Otherwise it returns the index of the range with the equal
     * min and max. 
     */
    private function findEqualRange(array $ranges)
    {
        $result = -1;
        for ($i = 0; $i < count($ranges); $i++) {
            $range = $ranges[$i];
            if ($range['min'] == $range['max']) {
                $result = $i;
                break;
            }
        }
        return $result;
    }

    public function mergeRanges(array $ranges, $outputLength)
    {
        // get rid of range elements with equal min and max first,
        // so we might meet the requirement for $outputLength
        $raw_ranges = $this->cleanupForEqualRanges($ranges);
        if ($outputLength < count($ranges)) {
            // this loop is merging ranges until we meet the
            // requirement. the use of the index makes sure
            // that we're not always merging the first element,
            // so the merging is more or less distributed
            $idx = 0;
            while ($outputLength < count($raw_ranges)) {
                $raw_ranges = $this->combineRange($raw_ranges, $idx);
                $idx        = ($idx + 1) % count($raw_ranges);
            }
        }
        return $raw_ranges;
    }
}
