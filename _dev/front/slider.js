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

import getQueryParameters from './urlparser';
import NumberFormatter from '../cldr/number-formatter';

const formatters = {};

const displayLabelBlock = (formatterId, displayBlock, min, max) => {
  if (formatters[formatterId] === undefined) {
    displayBlock.text(
      displayBlock.text().replace(
        /([^\d]*)(?:[\d\s.,]+)([^\d]+)(?:[\d\s.,]+)(.*)/,
        `$1${min}$2${max}$3`,
      ),
    );
  } else {
    displayBlock.text(
      `${formatters[formatterId].format(min)} - ${formatters[formatterId].format(max)}`,
    );
  }
};

/**
 * Refresh facets sliders
 */
const refreshSliders = () => {
  $('.faceted-slider').each(function initializeSliders() {
    const $el = $(this);
    const values = $el.data('slider-values');
    const specifications = $el.data('slider-specifications');

    if (specifications !== null && specifications !== undefined) {
      formatters[$el.data('slider-id')] = NumberFormatter.build(specifications);
    }

    displayLabelBlock(
      $el.data('slider-id'),
      $(`#facet_label_${$el.data('slider-id')}`),
      values === null ? $el.data('slider-min') : values[0],
      values === null ? $el.data('slider-max') : values[1],
    );

    $(`#slider-range_${$el.data('slider-id')}`).slider({
      range: true,
      min: $el.data('slider-min'),
      max: $el.data('slider-max'),
      values: [
        values === null ? $el.data('slider-min') : values[0],
        values === null ? $el.data('slider-max') : values[1],
      ],
      stop(event, ui) {
        const nextEncodedFacetsURL = $el.data('slider-encoded-url');
        const urlsSplitted = nextEncodedFacetsURL.split('?');
        let queryParams = [];

        // Retrieve parameters if exists
        if (urlsSplitted.length > 1) {
          queryParams = getQueryParameters(urlsSplitted[1]);
        }

        let found = false;
        queryParams.forEach((query) => {
          if (query.name === 'q') {
            found = true;
          }
        });

        if (!found) {
          queryParams.push({name: 'q', value: ''});
        }

        // Update query parameter
        queryParams.forEach((query) => {
          if (query.name === 'q') {
            // eslint-disable-next-line
            query.value += [
              query.value.length > 0 ? '/' : '',
              $el.data('slider-label'),
              '-',
              $el.data('slider-unit'),
              '-',
              ui.values[0],
              '-',
              ui.values[1],
            ].join('');
          }
        });

        const requestUrl = [
          urlsSplitted[0],
          '?',
          $.param(queryParams),
        ].join('');

        prestashop.emit(
          'updateFacets',
          requestUrl,
        );
      },
      slide(event, ui) {
        displayLabelBlock(
          $el.data('slider-id'),
          $(`#facet_label_${$el.data('slider-id')}`),
          ui.values[0],
          ui.values[1],
        );
      },
    });
  });
};

export default refreshSliders;
