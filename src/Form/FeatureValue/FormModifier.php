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

namespace PrestaShop\Module\FacetedSearch\Form\FeatureValue;

use Context;
use PrestaShop\Module\FacetedSearch\Constraint\UrlSegment;
use PrestaShopBundle\Form\Admin\Type\TranslatableType;
use PrestaShopBundle\Translation\TranslatorComponent;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Adds module specific fields to BO form
 */
class FormModifier
{
    /**
     * @var Context
     */
    private $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    public function modify(
        FormBuilderInterface $formBuilder,
        array $data
    ) {
        /**
         * @var TranslatorComponent
         */
        $translator = $this->context->getTranslator();
        $invalidCharsHint = $translator->trans(
            'Invalid characters: <>;=#{}_',
            [],
            'Modules.Facetedsearch.Admin'
        );

        $urlTip = $translator->trans(
            'When the Faceted Search module is enabled, you can get more detailed URLs by choosing ' .
            'the word that best represents this feature. By default, PrestaShop uses the ' .
            'feature\'s value, but you can change that setting using this field.',
            [],
            'Modules.Facetedsearch.Admin'
        );
        $metaTitleTip = $translator->trans(
            'When the Faceted Search module is enabled, you can get more detailed page titles by ' .
            'choosing the word that best represents this feature. By default, PrestaShop uses the ' .
            'feature\'s value, but you can change that setting using this field.',
            [],
            'Modules.Facetedsearch.Admin'
        );

        $formBuilder
            ->add(
                'url_name',
                TranslatableType::class,
                [
                    'required' => false,
                    'label' => $translator->trans('URL', [], 'Modules.Facetedsearch.Admin'),
                    'help' => $urlTip . ' ' . $invalidCharsHint,
                    'options' => [
                        'constraints' => [
                            new UrlSegment([
                                'message' => $translator->trans('%s is invalid.', [], 'Admin.Notifications.Error'),
                            ]),
                        ],
                    ],
                    'data' => $data['url'],
                ]
            )
            ->add(
                'meta_title',
                TranslatableType::class,
                [
                    'required' => false,
                    'label' => $translator->trans('Meta title', [], 'Modules.Facetedsearch.Admin'),
                    'help' => $metaTitleTip,
                    'data' => $data['meta_title'],
                ]
            );
    }
}
