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

namespace PrestaShop\Module\FacetedSearch\Hook;

class ProductComment extends AbstractHook
{
    const AVAILABLE_HOOKS = [
        'actionObjectProductCommentValidateAfter',
    ];

    /**
     * Product Comment Validate After
     *
     * Smart index after validating a comment
     *
     * @param array $params
     */
    public function actionObjectProductCommentValidateAfter(array $params)
    {
        $grade = $params['object'];

        $commentLogRow = $this->database->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'layered_comment_index_log`
            WHERE id_comment = ' . $grade->id . ' AND indexed = 1');

        if (empty($commentLogRow)){
            $gradeCommentRow = $this->database->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'layered_comment_index`
            WHERE id_product =' . $grade->id_product);

            if (empty($gradeCommentRow) || !$gradeCommentRow){
                //insert the first index record for this comment
                $this->database->execute('INSERT INTO `'._DB_PREFIX_.'layered_comment_index` (`id_product`, `score`, `avg_score`) VALUES ('.$grade->id_product.', '.$grade->grade.','.$grade->grade.')');

                $this->addCommentIndexLog($grade);
            }else{
                //update index for the comment
                $productCommentLogRow = $this->database->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'layered_comment_index_log`
            WHERE id_product = ' . $grade->id_product . ' AND indexed = 1');

                $newGradeValue = (int)$gradeCommentRow[0]['score'] + (int)$grade->grade;
                $avg_score = $newGradeValue/(count($productCommentLogRow)+1);

                $this->database->execute('update ' . _DB_PREFIX_ . 'layered_comment_index set score='.$newGradeValue.', avg_score= '.$avg_score .' where id_product=' . $grade->id_product);

                $this->addCommentIndexLog($grade);
            }
        }
    }

    public function addCommentIndexLog($comment){
        $this->database->execute('INSERT INTO `'._DB_PREFIX_.'layered_comment_index_log` (`id_comment`, `indexed`, `id_product`) VALUES ('.$comment->id.', 1,'. $comment->id_product .')');
    }
}
