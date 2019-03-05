function formatter(number) {
  return number;
}

/**
 * Refresh facets sliders
 */
function refreshSliders() {
  $('.faceted-slider').each(function initializeSliders() {
    const $el = $(this);
    const $values = $el.data('slider-values');

    $(`#slider-range_${$el.data('slider-id')}`).slider({
      range: true,
      min: $el.data('slider-min'),
      max: $el.data('slider-max'),
      values: [
        $values[0],
        $values[1],
      ],
      change(event, ui) {
        const nextEncodedFacetsURL = $el.data('slider-encoded-url');
        // because spaces are replaces with %20, and sometimes by +, we want to keep the + sign
        const nextEncodedFacets = escape($el.data('slider-encoded-facets')).replace(/%20/g, '+');
        prestashop.emit(
          'updateFacets',
          nextEncodedFacetsURL.replace(
            nextEncodedFacets,
            nextEncodedFacets.replace(
              `${$el.data('slider-min')}-${$el.data('slider-max')}`,
              `${ui.values[0]}-${ui.values[1]}`,
            ),
          ),
        );
      },
      slide(event, ui) {
        const $displayBlock = $(`#facet_label_${$el.data('slider-id')}`);
        $displayBlock.text(
          $displayBlock.text().replace(
            /([^\d]*)(?:[\d \.,]+)([^\d]+)(?:[\d \.,]+)(.*)/,
            `$1${formatter(ui.values[0])}$2${formatter(ui.values[1])}$3`,
          ),
        );
      },
    });
  });
}

$(document).ready(() => {
  prestashop.on('updateProductList', (data) => {
    refreshSliders(data);
  });

  refreshSliders();
});
