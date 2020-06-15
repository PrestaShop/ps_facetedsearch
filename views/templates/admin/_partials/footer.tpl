{**
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
 *}
<div class="panel-footer" id="toolbar-footer">
  <button class="btn btn-default pull-right" id="submit-filter" name="SubmitFilter" type="submit"><i class="process-icon-save"></i> <span>{l s='Save' d='Admin.Actions'}</span></button>
  <a class="btn btn-default" href="{$current_url}">
    <i class="process-icon-cancel"></i> <span>{l s='Cancel' d='Admin.Actions'}</span>
  </a>
</div>

<script type="text/javascript">
  var translations = new Array();
  {if isset($filters)}var filters = '{$filters|@json_encode}';{/if}
  translations['no_selected_categories'] = "{l s='You must select at least one category' d='Modules.Facetedsearch.Admin'}";
  translations['no_selected_filters'] = "{l s='You must select at least one filter' d='Modules.Facetedsearch.Admin'}";
</script>
