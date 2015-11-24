<?php

require_once implode(DIRECTORY_SEPARATOR, [
    __DIR__,
    '..', 'src', 'BlockLayeredRangeAggregator.php'
]);

class BlockLayeredRangeAggregatorTest extends PHPUnit_Framework_TestCase
{
    public function test_ranges_are_aggregated_simple()
    {
        $ranges = [
            ['price_min' => 16, 'price_max' => 20],
            ['price_min' => 26, 'price_max' => 32],
            ['price_min' => 25, 'price_max' => 31],
            ['price_min' => 50, 'price_max' => 61],
            ['price_min' => 28, 'price_max' => 35],
            ['price_min' => 30, 'price_max' => 37],
            ['price_min' => 16, 'price_max' => 20]
        ];

        $aggregator = new BlockLayeredRangeAggregator;

        $actual = $aggregator->aggregateRanges($ranges, 'price_min', 'price_max');

        $this->assertEquals([
            'min' => 16,
            'max' => 61,
            'ranges' => [
                ['min' => 16, 'max' => 20, 'count' => 2],
                ['min' => 25, 'max' => 37, 'count' => 4],
                ['min' => 50, 'max' => 61, 'count' => 1]
            ]
        ], $actual);
    }

    public function test_ranges_are_aggregated_big_overlap()
    {
        $ranges = [
            ['price_min' => 16, 'price_max' => 20],
            ['price_min' => 26, 'price_max' => 32],
            ['price_min' => 25, 'price_max' => 31],
            ['price_min' => 50, 'price_max' => 61],
            ['price_min' => 28, 'price_max' => 35],
            ['price_min' => 30, 'price_max' => 37],
            ['price_min' => 16, 'price_max' => 20],
            ['price_min' => 25, 'price_max' => 61]
        ];

        $aggregator = new BlockLayeredRangeAggregator;

        $actual = $aggregator->aggregateRanges($ranges, 'price_min', 'price_max');

        $this->assertEquals([
            'min' => 16,
            'max' => 61,
            'ranges' => [
                ['min' => 16, 'max' => 20, 'count' => 2],
                ['min' => 25, 'max' => 61, 'count' => 6]
            ]
        ], $actual);

    }
}
