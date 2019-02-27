$(document).ready(function() {
  /**
   * Refresh facets sliders
   */
  function refreshSliders() {
    $('.faceted-slider').each(function() {
      const $el = $(this);
      $('#slider-range_' + $el.data('slider-id')).slider({
        range: true,
        min: $el.data('slider-min'),
        max: $el.data('slider-max'),
        values: [
          $el.data('slider-min'),
          $el.data('slider-max')
        ],
        change: function(event, ui) {
          const nextEncodedFacetsURL = $el.data('slider-encoded-url');
          // because spaces are replaces with %20, and sometimes by +, we want to keep the + sign
          const nextEncodedFacets = escape($el.data('slider-encoded-facets')).replace(/%20/g,'+');
          prestashop.emit(
            'updateFacets',
            nextEncodedFacetsURL.replace(
              nextEncodedFacets,
              nextEncodedFacets.replace(
                $el.data('slider-min') + '-' + $el.data('slider-max'),
                ui.values[0] + '-' + ui.values[1]
              )
            )
          );
        },
        slide: function(event, ui) {
          $('#amount_' + $el.data('slider-id')).val(ui.values[0] + ' - ' + ui.values[1]);
        }
      });

      $('#amount_' + $el.data('slider-id')).val($el.data('slider-min') + ' - ' + $el.data('slider-max'));
    });
  }

  prestashop.on('updateProductList', (data) => {
    refreshSliders(data);
  });

  refreshSliders();
});
