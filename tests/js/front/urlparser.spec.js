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
import {expect} from 'chai';
import getQueryParameters from '../../../_dev/front/urlparser';

describe('getQueryParameters', () => {
  const assertions = [
    [
      'q=%C3%89tat-Nouveau%2FPrix-%E2%82%AC-22-42',
      [{
        name: 'q',
        value: 'État-Nouveau/Prix-€-22-42',
      }],
    ],
    [
      'q=Prix-%E2%82%AC-22-42/Composition-Carton recycl%C3%A9',
      [{
        name: 'q',
        value: 'Prix-€-22-42/Composition-Carton recyclé',
      }],
    ],
    [
      'q=Prix-%E2%82%AC-22-42/Composition-Carton recycl%C3%A9&something=thisIsSparta',
      [
        {
          name: 'q',
          value: 'Prix-€-22-42/Composition-Carton recyclé',
        },
        {
          name: 'something',
          value: 'thisIsSparta',
        },
      ],
    ],
  ];
  assertions.forEach((assertion) => {
    it(`test ${assertion[0]}`, () => {
      expect(getQueryParameters(assertion[0])).to.eql(assertion[1]);
    });
  });
});
