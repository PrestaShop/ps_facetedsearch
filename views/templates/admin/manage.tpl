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
{include file='./_partials/messages.tpl'}

<div class="panel">
  <h3><i class="icon-cogs"></i> {l s='Indexes and caches' d='Modules.Facetedsearch.Admin'}</h3>
  <div id="indexing-warning" class="alert alert-warning" style="display: none">
	{l s='Indexing is in progress. Please do not leave this page' d='Modules.Facetedsearch.Admin'}
  </div>
  <div class="row">
	<p>
	  <a class="ajaxcall-recurcive btn btn-default" href="{$price_indexer_url}">{l s='Index all missing prices' d='Modules.Facetedsearch.Admin'}</a>
	  <a class="ajaxcall-recurcive btn btn-default" href="{$full_price_indexer_url}">{l s='Rebuild entire price index' d='Modules.Facetedsearch.Admin'}</a>
	  <a class="ajaxcall btn btn-default" href="{$attribute_indexer_url}">{l s='Build attributes and features indexes' d='Modules.Facetedsearch.Admin'}</a>
	  <a class="ajaxcall btn btn-default" href="{$clear_cache_url}">{l s='Clear cache' d='Modules.Facetedsearch.Admin'}</a>
	</p>
  </div>
  <div class="row">
	<div class="alert alert-info">
	  {l s='You can set a cron job that will rebuild price index using the following URL:' d='Modules.Facetedsearch.Admin'}
	  <br>
	  <strong>{$price_indexer_url}</strong>
	  <br>
	  <br>
	  {l s='You can set a cron job that will rebuild attribute index using the following URL:' d='Modules.Facetedsearch.Admin'}
	  <br>
	  <strong>{$attribute_indexer_url}</strong>
	</div>
  </div>
  <div class="row">
	<div class="alert alert-info">{l s='A nightly rebuild is recommended.' d='Modules.Facetedsearch.Admin'}</div>
  </div>
</div>
<div class="panel">
  <h3><i class="icon-cogs"></i> {l s='Filters templates' d='Modules.Facetedsearch.Admin'}<span class="badge">{$filters_templates|count}</span></h3>
  {if $filters_templates|count > 0}
	<div class="row">
	  <table class="table">
		<thead>
		  <tr>
			<th class="fixed-width-xs center"><span class="title_box">{l s='ID' d='Admin.Global'}</span></th>
			<th><span class="title_box text-left">{l s='Name' d='Admin.Global'}</span></th>
			<th><span class="title_box">{l s='Pages' d='Admin.Global'}</span></th>
			<th class="fixed-width-sm center"><span class="title_box">{l s='Categories' d='Admin.Global'}</span></th>
			<th class="fixed-width-lg"><span class="title_box">{l s='Created on' d='Modules.Facetedsearch.Admin'}</span></th>
			<th class="fixed-width-sm"><span class="title_box text-right">{l s='Actions' d='Modules.Facetedsearch.Admin'}</span></th>
		  </tr>
		</thead>
		<tbody>
		  {foreach $filters_templates as $template}
			<tr>
			  <td class="center">{(int)$template['id_layered_filter']}</td>
			  <td class="text-left">{$template['name']}</td>
			  <td>{$template['controllers']}</td>
			  <td class="center">{(int)$template['n_categories']}</td>
			  <td>{Tools::displayDate($template['date_add'],null , true)}</td>
			  <td>
				{if empty($limit_warning)}
				  <div class="btn-group-action">
					<div class="btn-group pull-right">
					  <a href="{$current_url}&amp;edit_filters_template=1&amp;id_layered_filter={(int)$template['id_layered_filter']}" class="btn btn-default">
						<i class="icon-pencil"></i> {l s='Edit' d='Admin.Actions'}
					  </a>
					  <button class="btn btn-default dropdown-toggle" data-toggle="dropdown">
						<span class="caret"></span>&nbsp;
					  </button>
					  <ul class="dropdown-menu">
						<li>
						  <a href="{$current_url}&amp;deleteFilterTemplate=1&amp;id_layered_filter={(int)$template['id_layered_filter']}"
						     onclick="return confirm('{l s='Do you really want to delete this filter template?' d='Modules.Facetedsearch.Admin'}');">
							<i class="icon-trash"></i> {l s='Delete' d='Admin.Actions'}
						  </a>
						</li>
					  </ul>
					</div>
				  </div>
				{/if}
			  </td>
			</tr>
		  {/foreach}
		</tbody>
	  </table>
	  <div class="clearfix">&nbsp;</div>
	</div>
  {else}
	<div class="row alert alert-warning">{l s='No filter template found.' d='Modules.Facetedsearch.Admin'}</div>
  {/if}
  {if empty($limit_warning)}
	<div class="panel-footer">
	  <a class="btn btn-default pull-right" href="{$current_url}&amp;add_new_filters_template=1"><i class="process-icon-plus"></i> {l s='Add new template' d='Modules.Facetedsearch.Admin'}</a>
	</div>
  {/if}
</div>
<div class="panel">
  <h3><i class="icon-cogs"></i> {l s='Configuration' d='Admin.Global'}</h3>
  <form action="{$current_url}" method="post" class="form-horizontal">
	<div class="form-group">
	  <label class="col-lg-3 control-label">{l s='Enable cache system' d='Modules.Facetedsearch.Admin'}</label>
	  <div class="col-lg-9">
		<span class="switch prestashop-switch fixed-width-lg">
		  <input type="radio" name="ps_layered_cache_enabled" id="ps_layered_cache_enabled_on" value="1"{if $cache_enabled} checked="checked"{/if}>
		  <label for="ps_layered_cache_enabled_on" class="radioCheck">
			<i class="color_success"></i> {l s='Yes' d='Admin.Global'}
		  </label>
		  <input type="radio" name="ps_layered_cache_enabled" id="ps_layered_cache_enabled_off" value="0"{if !$cache_enabled} checked="checked"{/if}>
		  <label for="ps_layered_cache_enabled_off" class="radioCheck">
			<i class="color_danger"></i> {l s='No' d='Admin.Global'}
		  </label>
		  <a class="slide-button btn"></a>
		</span>
	  </div>
	</div>

	<div class="form-group">
	  <label class="col-lg-3 control-label">{l s='Show the number of matching products' d='Modules.Facetedsearch.Admin'}</label>
	  <div class="col-lg-9">
		<span class="switch prestashop-switch fixed-width-lg">
		  <input type="radio" name="ps_layered_show_qties" id="ps_layered_show_qties_on" value="1"{if $show_quantities} checked="checked"{/if}>
		  <label for="ps_layered_show_qties_on" class="radioCheck">
			<i class="color_success"></i> {l s='Yes' d='Admin.Global'}
		  </label>
		  <input type="radio" name="ps_layered_show_qties" id="ps_layered_show_qties_off" value="0"{if !$show_quantities} checked="checked"{/if}>
		  <label for="ps_layered_show_qties_off" class="radioCheck">
			<i class="color_danger"></i> {l s='No' d='Admin.Global'}
		  </label>
		  <a class="slide-button btn"></a>
		</span>
	  </div>
	</div>

	<div class="form-group">
	  <label class="col-lg-3 control-label">{l s='Show products from subcategories' d='Modules.Facetedsearch.Admin'}</label>
	  <div class="col-lg-9">
		<span class="switch prestashop-switch fixed-width-lg">
		  <input type="radio" name="ps_layered_full_tree" id="ps_layered_full_tree_on" value="1"{if $full_tree} checked="checked"{/if}>
		  <label for="ps_layered_full_tree_on" class="radioCheck">
			<i class="color_success"></i> {l s='Yes' d='Admin.Global'}
		  </label>
		  <input type="radio" name="ps_layered_full_tree" id="ps_layered_full_tree_off" value="0"{if !$full_tree} checked="checked"{/if}>
		  <label for="ps_layered_full_tree_off" class="radioCheck">
			<i class="color_danger"></i> {l s='No' d='Admin.Global'}
		  </label>
		  <a class="slide-button btn"></a>
		</span>
	  </div>
	</div>

	<div class="form-group">
	  <label class="col-lg-3 control-label">{l s='Show products only from default category' d='Modules.Facetedsearch.Admin'}</label>
	  <div class="col-lg-9">
		<span class="switch prestashop-switch fixed-width-lg">
		  <input type="radio" name="ps_layered_filter_by_default_category" id="ps_layered_filter_by_default_category_on" value="1"{if $filter_by_default_category} checked="checked"{/if}>
		  <label for="ps_layered_filter_by_default_category_on" class="radioCheck">
			<i class="color_success"></i> {l s='Yes' d='Admin.Global'}
		  </label>
		  <input type="radio" name="ps_layered_filter_by_default_category" id="ps_layered_filter_by_default_category_off" value="0"{if !$filter_by_default_category} checked="checked"{/if}>
		  <label for="ps_layered_filter_by_default_category_off" class="radioCheck">
			<i class="color_danger"></i> {l s='No' d='Admin.Global'}
		  </label>
		  <a class="slide-button btn"></a>
		</span>
	  </div>
	  <div class="col-lg-9 col-lg-offset-3">
		<div class="help-block">
		  {l s='Works only if "Show products from subcategories" is off.' d='Modules.Facetedsearch.Admin'}
		</div>
	  </div>
	</div>

	<div class="form-group">
	  <label class="col-lg-3 control-label">{l s='Category filter depth (0 for no limits, 1 by default)' d='Modules.Facetedsearch.Admin'}</label>
	  <div class="col-lg-9">
		<input type="text" name="ps_layered_filter_category_depth" value="{if $category_depth !== false}{$category_depth}{else}1{/if}" class="fixed-width-sm" />
	  </div>
	</div>

	<div class="form-group">
	  <label class="col-lg-3 control-label">{l s='Use tax to filter price' d='Modules.Facetedsearch.Admin'}</label>
	  <div class="col-lg-9">
		<span class="switch prestashop-switch fixed-width-lg">
		  <input type="radio" name="ps_layered_filter_price_usetax" id="ps_layered_filter_price_usetax_on" value="1"{if $price_use_tax} checked="checked"{/if}>
		  <label for="ps_layered_filter_price_usetax_on" class="radioCheck">
			<i class="color_success"></i> {l s='Yes' d='Admin.Global'}
		  </label>
		  <input type="radio" name="ps_layered_filter_price_usetax" id="ps_layered_filter_price_usetax_off" value="0"{if !$price_use_tax} checked="checked"{/if}>
		  <label for="ps_layered_filter_price_usetax_off" class="radioCheck">
			<i class="color_danger"></i> {l s='No' d='Admin.Global'}
		  </label>
		  <a class="slide-button btn"></a>
		</span>
	  </div>
	</div>

	<div class="form-group">
	  <label class="col-lg-3 control-label">{l s='Use rounding to filter price' d='Modules.Facetedsearch.Admin'}</label>
	  <div class="col-lg-9">
		<span class="switch prestashop-switch fixed-width-lg">
		  <input type="radio" name="ps_layered_filter_price_rounding" id="ps_layered_filter_price_rounding_on" value="1"{if $price_use_rounding} checked="checked"{/if}/>
		  <label for="ps_layered_filter_price_rounding_on" class="radioCheck">
			<i class="color_success"></i> {l s='Yes' d='Admin.Global'}
		  </label>
		  <input type="radio" name="ps_layered_filter_price_rounding" id="ps_layered_filter_price_rounding_off" value="0"{if !$price_use_rounding} checked="checked"{/if}/>
		  <label for="ps_layered_filter_price_rounding_off" class="radioCheck">
			<i class="color_danger"></i> {l s='No' d='Admin.Global'}
		  </label>
		  <a class="slide-button btn"></a>
		</span>
	  </div>
	</div>

	<div class="form-group">
	  <label class="col-lg-3 control-label">{l s='Show unavailable, out of stock last' d='Modules.Facetedsearch.Admin'}</label>
	  <div class="col-lg-9">
		<span class="switch prestashop-switch fixed-width-lg">
		  <input type="radio" name="ps_layered_filter_show_out_of_stock_last" id="ps_layered_filter_show_out_of_stock_last_on" value="1"{if $show_out_of_stock_last} checked="checked"{/if}/>
		  <label for="ps_layered_filter_show_out_of_stock_last_on" class="radioCheck">
			<i class="color_success"></i> {l s='Yes' d='Admin.Global'}
		  </label>
		  <input type="radio" name="ps_layered_filter_show_out_of_stock_last" id="ps_layered_filter_show_out_of_stock_last_off" value="0"{if !$show_out_of_stock_last} checked="checked"{/if}/>
		  <label for="ps_layered_filter_show_out_of_stock_last_off" class="radioCheck">
			<i class="color_danger"></i> {l s='No' d='Admin.Global'}
		  </label>
		  <a class="slide-button btn"></a>
		</span>
	  </div>
	</div>

  <div class="form-group">
    <label class="col-lg-3 control-label">{l s='Use Jquery UI slider' d='Modules.Facetedsearch.Admin'}</label>
    <div class="col-lg-9">
    <span class="switch prestashop-switch fixed-width-lg">
      <input type="radio" name="ps_use_jquery_ui_slider" id="ps_use_jquery_ui_slider_on" value="1"{if $use_jquery_ui_slider} checked="checked"{/if}>
      <label for="ps_use_jquery_ui_slider_on" class="radioCheck">
      <i class="color_success"></i> {l s='Yes' d='Admin.Global'}
      </label>
      <input type="radio" name="ps_use_jquery_ui_slider" id="ps_use_jquery_ui_slider_off" value="0"{if !$use_jquery_ui_slider} checked="checked"{/if}>
      <label for="ps_use_jquery_ui_slider_off" class="radioCheck">
      <i class="color_danger"></i> {l s='No' d='Admin.Global'}
      </label>
      <a class="slide-button btn"></a>
    </span>
    </div>
    <div class="col-lg-9 col-lg-offset-3">
      <div class="help-block">
        {l s='Switch this off only if your theme does not use Jquery UI slider. It is recommended to keep it on when using classic theme.' d='Modules.Facetedsearch.Admin'}
      </div>
    </div>
  </div>

	<div class="form-group">
		<label class="control-label col-lg-3">{l s='Default filter template for new categories' d='Modules.Facetedsearch.Admin'}</label>				
		<div class="col-lg-9">
			<select class="form-control fixed-width-xxl" name="ps_layered_default_category_template" id="ps_layered_default_category_template">
				<option value="0" {if empty($default_category_template)} selected="selected" {/if}>{l s='None' d='Admin.Global'}</option>
				{foreach $filters_templates as $template}
					<option value="{$template['id_layered_filter']}" {if $default_category_template == $template['id_layered_filter']} selected="selected" {/if}>{$template['name']}</option>
				{/foreach}
			</select>
		</div>
		<div class="col-lg-9 col-lg-offset-3">
			<div class="help-block">{l s='If you want to automatically assign a filter template to new categories, select it here.' d='Modules.Facetedsearch.Admin'}</div>
		</div>
	</div>

	<div class="panel-footer">
	  <button type="submit" class="btn btn-default pull-right" name="submitLayeredSettings"><i class="process-icon-save"></i> {l s='Save' d='Admin.Actions'}</button>
	</div>
  </form>
</div>

<script type="text/javascript">
  {if isset($PS_LAYERED_INDEXED)}var PS_LAYERED_INDEXED = {$PS_LAYERED_INDEXED};{/if}
  var token = '{$token}';
  var id_lang = {$id_lang};
  var base_folder = '{$base_folder}';
  var translations = new Object();

  translations.in_progress = '{l s='(in progress)' js=1 d='Modules.Facetedsearch.Admin'}';
  translations.url_indexation_finished = '{l s='URL indexing finished' js=1 d='Modules.Facetedsearch.Admin'}';
  translations.attribute_indexation_finished = '{l s='Attribute indexing finished' js=1 d='Modules.Facetedsearch.Admin'}';
  translations.url_indexation_failed = '{l s='URL indexing failed' js=1 d='Modules.Facetedsearch.Admin'}';
  translations.attribute_indexation_failed = '{l s='Attribute indexing failed' js=1 d='Modules.Facetedsearch.Admin'}';
  translations.price_indexation_finished = '{l s='Price indexing finished' js=1 d='Modules.Facetedsearch.Admin'}';
  translations.price_indexation_failed = '{l s='Price indexing failed' js=1 d='Modules.Facetedsearch.Admin'}';
  translations.price_indexation_in_progress = '{l s='(in progress, %s products price to index)' js=1 d='Modules.Facetedsearch.Admin'}';
  translations.loading = '{l s='Loading...' js=1 d='Modules.Facetedsearch.Admin'}';
  translations.delete_all_filters_templates = '{l s='You selected -All categories-: all existing filter templates will be deleted. Is it OK?' js=1 d='Modules.Facetedsearch.Admin'}';
  translations.no_selected_categories = '{l s='You must select at least one category' js=1 d='Modules.Facetedsearch.Admin'}';
</script>
