<?php
namespace NativeModuleFacetedSearchBundle\Adapter;

class FacetedSearchMySQLAdapter extends FacetedSearchAbstract {
    function execute() {
        // TODO: Implement execute() method.
        return array();
    }

    function getMinMaxValue($fieldName)
    {
        return [0 => 0, 1 => 100];
    }

    function getFieldRanges($fieldName, $outputLength)
    {
        $diff = 'SELECT ROUND((-MIN(p.price) + MAX(p.price)) / '.$outputLength.') AS diff FROM ps_category c STRAIGHT_JOIN ps_category_product cp ON (c.id_category = cp.id_category AND c.nleft >= 17 AND c.nright <= 78 AND c.active = 1) STRAIGHT_JOIN ps_product_shop product_shop ON (product_shop.id_product = cp.id_product) STRAIGHT_JOIN ps_product p ON (p.id_product=cp.id_product)';
        $outputRange = 'SELECT FLOOR(p.price/'.$diff.')*'.$diff.' as range_start, (FLOOR(p.price/'.$diff.')+1)*'.$diff.'-1 as range_end, COUNT(DISTINCT(p.id_product)) c FROM ps_category c STRAIGHT_JOIN ps_category_product cp ON (c.id_category = cp.id_category AND c.nleft >= 17 AND c.nright <= 78 AND c.active = 1) STRAIGHT_JOIN ps_product_shop product_shop ON (product_shop.id_product = cp.id_product) STRAIGHT_JOIN ps_product p ON (p.id_product=cp.id_product) GROUP BY FLOOR(p.price / '.$diff.')';
        return [[0 /*'range_start'*/ => '0', 1 /*'range_end'*/ => '100', 'nbr' => '0']];
    }

    function count() {
        return 0;
    }
}