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
        'actionObjectProductCommentAddAfter',
        'actionObjectProductCommentDeleteAfter',
        'actionObjectProductCommentValidateAfter'
    ];

    /**
     * Product Add After
     *
     * Smart index after adding a comment
     *
     * @param array $params
     */
    public function actionObjectProductCommentAddAfter(array $params)
    {
        if (!\Configuration::get('PRODUCT_COMMENTS_MODERATE')){
            $this->updateCommentIndex($params['array']);
        }
    }

    /**
     * Product Validate After
     *
     * Smart index after validating a comment
     *
     * @param array $params
     */
    public function actionObjectProductCommentValidateAfter(array $params){
        if (\Configuration::get('PRODUCT_COMMENTS_MODERATE')){
            $this->updateCommentIndex($params['object']);
        }
    }

    /**
     * Product Delete After
     *
     * Smart index after deleting a comment
     *
     * @param array $params
     */
    public function actionObjectProductCommentDeleteAfter(array $params){
        $comment = $params['object']; $skip = false;

        if (\Configuration::get('PRODUCT_COMMENTS_MODERATE')){
            if (!(int)$comment->validate){
                $skip = true;
            }
        }

        if (!$skip){
            $gradeCommentRow = $this->database->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'layered_comment_index`
            WHERE id_product =' . $comment->id_product);

            //update index for the comment
            $productCommentLogRowCount = $this->database->executeS('SELECT count(id_comment) as count FROM `' . _DB_PREFIX_ . 'layered_comment_index_log`
            WHERE id_product = ' . $comment->id_product . ' AND indexed = 1');

            $count = $productCommentLogRowCount[0]['count'];
            $count--;

            $newGradeValue = (int)$gradeCommentRow[0]['score'] - (int)$comment->grade;
            $avg_score = $newGradeValue/(((int)$count));

            $this->database->execute('update ' . _DB_PREFIX_ . 'layered_comment_index set score='.$newGradeValue.', avg_score= '.$avg_score .' where id_product=' . $comment->id_product);

            $this->deleteCommentIndexLog($comment->id, $comment->id_product);
        }
    }

    public function updateCommentIndex($param){
        if (is_array($param)){
            $grade = $param['grade'];
            $id_product = $param['id_product'];
            $id_comment = $param['id_product_comment'];
        }else{
            $grade = $param->grade;
            $id_product = $param->id_product;
            $id_comment = $param->id;
        }

        $commentLogRow = $this->database->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'layered_comment_index_log`
            WHERE id_comment = ' . $id_comment . ' AND indexed = 1');

        if (empty($commentLogRow)){
            $gradeCommentRow = $this->database->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'layered_comment_index`
            WHERE id_product =' . $id_product);

            if (empty($gradeCommentRow)){
                //insert the first index record for this comment
                $this->database->execute('INSERT INTO `'._DB_PREFIX_.'layered_comment_index` (`id_product`, `score`, `avg_score`) VALUES ('.$id_product.', '.$grade.','.$grade.')');

                $this->addCommentIndexLog($id_comment, $id_product);
            }else{
                //update index for the comment
                $productCommentLogRowCount = $this->database->executeS('SELECT count(id_comment) as count FROM `' . _DB_PREFIX_ . 'layered_comment_index_log`
            WHERE id_product = ' . $id_product . ' AND indexed = 1');

                $count = $productCommentLogRowCount[0]['count'];

                $newGradeValue = (int)$gradeCommentRow[0]['score'] + (int)$grade;
                $avg_score = $newGradeValue/(((int)$count)+1);

                $this->database->execute('update ' . _DB_PREFIX_ . 'layered_comment_index set score='.$newGradeValue.', avg_score= '.$avg_score .' where id_product=' . $id_product);

                $this->addCommentIndexLog($id_comment, $id_product);
            }
        }
        $this->module->invalidateLayeredFilterBlockCache();
    }

    public function addCommentIndexLog($id_comment, $id_product){
        $this->database->execute('INSERT INTO `'._DB_PREFIX_.'layered_comment_index_log` (`id_comment`, `indexed`, `id_product`) VALUES ('.$id_comment.', 1,'. $id_product .')');
    }

    public function deleteCommentIndexLog($id_comment, $id_product){
        $this->database->execute('DELETE FROM `'._DB_PREFIX_.'layered_comment_index_log` WHERE id_comment = ' . $id_comment . ' AND id_product = ' . $id_product);
    }
}
