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
import './blocklayered.scss';

/* eslint-disable no-unused-vars, no-alert */
window.checkForm = function checkForm() {
  let isCategorySelected = false;
  let isFilterSelected = false;

  $('#categories-treeview input[type=checkbox]').each(function checkCategoriesCheckboxes() {
    if ($(this).prop('checked')) {
      isCategorySelected = true;
      return false;
    }
    return true;
  });

  $('.filter_list_item input[type=checkbox]').each(function checkFilterListCheckboxes() {
    if ($(this).prop('checked')) {
      isFilterSelected = true;
      return false;
    }
    return true;
  });

  if (!isCategorySelected) {
    alert(translations.no_selected_categories);
    $('#categories-treeview input[type=checkbox]').first().focus();
    return false;
  }

  if (!isFilterSelected) {
    alert(translations.no_selected_filters);
    $('#filter_list_item input[type=checkbox]').first().focus();
    return false;
  }

  return true;
};

$(document).ready(() => {
  $('.ajaxcall').click(function onAjaxCall() {
    if (this.legend === undefined) {
      this.legend = $(this).html();
    }

    if (this.running === undefined) {
      this.running = false;
    }

    if (this.running === true) {
      return false;
    }

    $('.ajax-message').hide();
    this.running = true;

    if (typeof (this.restartAllowed) === 'undefined' || this.restartAllowed) {
      $(this).html(this.legend + translations.in_progress);
      $('#indexing-warning').show();
    }

    this.restartAllowed = false;
    const type = $(this).attr('rel');

    $.ajax({
      url: `${this.href}&ajax=1`,
      context: this,
      dataType: 'json',
      cache: 'false',
      success() {
        this.running = false;
        this.restartAllowed = true;
        $('#indexing-warning').hide();
        $(this).html(this.legend);

        $('#ajax-message-ok span').html(
          type === 'price' ? translations.url_indexation_finished : translations.attribute_indexation_finished,
        );

        $('#ajax-message-ok').show();
      },
      error() {
        this.restartAllowed = true;
        $('#indexing-warning').hide();

        $('#ajax-message-ko span').html(
          type === 'price' ? translations.url_indexation_failed : translations.attribute_indexation_failed,
        );

        $('#ajax-message-ko').show();
        $(this).html(this.legend);
        this.running = false;
      },
    });

    return false;
  });

  let totalCount = 0;
  $('.ajaxcall-recurcive').each((it, elm) => {
    $(elm).click(function onAjaxRecursiveCall(e) {
      e.preventDefault();

      if (this.cursor === undefined) {
        this.cursor = 0;
      }

      if (this.legend === undefined) {
        this.legend = $(this).html();
      }

      if (this.running === undefined) {
        this.running = false;
      }

      if (this.running === true) {
        return false;
      }

      $('.ajax-message').hide();

      this.running = true;

      if (typeof (this.restartAllowed) === 'undefined' || this.restartAllowed) {
        $(this).html(this.legend + translations.in_progress);
        $('#indexing-warning').show();
      }

      this.restartAllowed = false;

      $.ajax({
        url: `${this.href}&ajax=1&cursor=${this.cursor}`,
        context: this,
        dataType: 'json',
        cache: 'false',
        success(res) {
          this.running = false;
          if (res.result) {
            this.cursor = 0;
            totalCount = 0;
            $('#indexing-warning').hide();
            $(this).html(this.legend);
            $('#ajax-message-ok span').html(translations.price_indexation_finished);
            $('#ajax-message-ok').show();
            return;
          }

          totalCount += parseInt(res.count, 10);
          this.cursor = parseInt(res.cursor, 10);
          $(this).html(
            this.legend + translations.price_indexation_in_progress.replace(
              '%s',
              `${totalCount}/${res.total}`,
            ),
          );
          $(this).click();
        },
        error(res) {
          this.restartAllowed = true;
          $('#indexing-warning').hide();
          $('#ajax-message-ko span').html(translations.price_indexation_failed);
          $('#ajax-message-ko').show();
          $(this).html(this.legend);

          this.cursor = 0;
          this.running = false;
        },
      });
      return false;
    });
  });

  if (typeof PS_LAYERED_INDEXED !== 'undefined' && PS_LAYERED_INDEXED) {
    $('#url-indexe').click();
    $('#full-index').click();
  }

  $('.sortable').sortable({
    forcePlaceholderSize: true,
  });

  $('.filter_list_item input[type=checkbox]').click(function onFilterLickItemCheckboxesClicked() {
    const currentSelectedFiltersCount = parseInt($('#selected_filters').html(), 10);

    $('#selected_filters').html(
      $(this).prop('checked') ? currentSelectedFiltersCount + 1 : currentSelectedFiltersCount - 1,
    );
  });

  if (typeof window.filters !== 'undefined') {
    const filters = JSON.parse(window.filters);
    let container = null;
    let $el;
    Object.keys(filters).forEach((filter) => {
      $el = $(`#${filter}`);
      $el.prop('checked', true);
      $('#selected_filters').html(parseInt($('#selected_filters').html(), 10) + 1);
      $(`select[name="${filter}_filter_type"]`).val(filters[filter].filter_type);
      $(`select[name="${filter}_filter_show_limit"]`).val(filters[filter].filter_show_limit);
      if (container === null) {
        container = $(`#${filter}`).closest('ul');
        $el.closest('li').detach().prependTo(container);
      } else {
        $el.closest('li').detach().insertAfter(container);
      }

      container = $el.closest('li');
    });
  }
});

$(document).on('ready', () => {
  const layeredDefaultCategory = $('input[name="ps_layered_filter_by_default_category"]');
  layeredDefaultCategory.on('change', function initializeOptions(event) {
    const elm = $(this);

    if (!elm.prop('checked')) {
      return;
    }

    if (elm.val() === '1') {
      $('input[name="ps_layered_full_tree"][value="0"]').prop('checked', true);
      $('input[name="ps_layered_full_tree"]').prop('disabled', true);
    } else {
      $('input[name="ps_layered_full_tree"]').prop('disabled', false);
    }
  });

  layeredDefaultCategory.filter('[value="1"]').trigger('change');
});
