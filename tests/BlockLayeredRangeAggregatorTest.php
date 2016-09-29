<?php

require_once implode(DIRECTORY_SEPARATOR, [
    __DIR__,
    '..', 'src', 'Ps_FacetedsearchRangeAggregator.php',
]);

class Ps_FacetedsearchRangeAggregatorTest extends PHPUnit_Framework_TestCase
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
            ['price_min' => 16, 'price_max' => 20],
        ];

        $aggregator = new Ps_FacetedsearchRangeAggregator();

        $actual = $aggregator->aggregateRanges($ranges, 'price_min', 'price_max');

        $this->assertEquals([
            'min' => 16,
            'max' => 61,
            'ranges' => [
                ['min' => 16, 'max' => 20, 'count' => 2],
                ['min' => 25, 'max' => 37, 'count' => 4],
                ['min' => 50, 'max' => 61, 'count' => 1],
            ],
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
            ['price_min' => 25, 'price_max' => 61],
        ];

        $aggregator = new Ps_FacetedsearchRangeAggregator();

        $actual = $aggregator->aggregateRanges($ranges, 'price_min', 'price_max');

        $this->assertEquals([
            'min' => 16,
            'max' => 61,
            'ranges' => [
                ['min' => 16, 'max' => 20, 'count' => 2],
                ['min' => 25, 'max' => 61, 'count' => 6],
            ],
        ], $actual);
    }

    public function test_ranges_are_merged()
    {
        $ranges = [
            ['min' => 16,    'max' => 18,    'count' => 1],
            ['min' => 20,    'max' => 30,    'count' => 3],
            ['min' => 40,    'max' => 62,    'count' => 5],
            ['min' => 80,    'max' => 100,   'count' => 7],
            ['min' => 120,   'max' => 130,   'count' => 9],
            ['min' => 130,   'max' => 140,   'count' => 11],
        ];

        $aggregator = new Ps_FacetedsearchRangeAggregator();

        $actual = $aggregator->mergeRanges($ranges, 3);
        $this->assertEquals([
            ['min' => 10,    'max' => 30,    'count' => 4],
            ['min' => 30,    'max' => 100,   'count' => 12],
            ['min' => 100,   'max' => 140,   'count' => 20],
        ], $actual);
    }

    public function test_ranges_are_merged_and_max_boudary_is_rounded()
    {
        $ranges = [
            ['min' => 10,    'max' => 30,    'count' => 4],
            ['min' => 30,    'max' => 97,   'count' => 12],
            ['min' => 100,   'max' => 140,   'count' => 20],
        ];

        $aggregator = new Ps_FacetedsearchRangeAggregator();

        $actual = $aggregator->mergeRanges($ranges, 3);
        $this->assertEquals([
            ['min' => 10,    'max' => 30,    'count' => 4],
            ['min' => 30,    'max' => 100,   'count' => 12],
            ['min' => 100,   'max' => 140,   'count' => 20],
        ], $actual);
    }
}
