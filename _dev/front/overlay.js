/**
 * 2007-2020 PrestaShop and Contributors
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2020 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

import './overlay.scss';

const template = `<div class="faceted-overlay">
<div class="overlay__inner">
<div class="overlay__content"><span class="spinner"></span></div>
</div>
</div>`;

function show() {
  if ($('.faceted-overlay').length === 1) {
    return;
  }

  $('body').append(template);
}

function hide() {
  $('.faceted-overlay').remove();
}

export {
  show as showOverlay,
  hide as hideOverlay,
};
