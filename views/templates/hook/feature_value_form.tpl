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
<div class="form-group">
	<label class="control-label col-lg-3">
		<span class="label-tooltip" data-toggle="tooltip" data-html="true" title="" data-original-title="{l s='Invalid characters: <>;=#{}_' d='Modules.Facetedsearch.Admin'}">{l s='URL' d='Modules.Facetedsearch.Admin'}</span>
	</label>
	<div class="col-lg-9">
		<div class="row">
			{foreach $languages as $language}
			  <div class="translatable-field lang-{$language['id_lang']}" style="display: {if $language['id_lang'] == $default_form_language}block{else}none{/if};">
				  <div class="col-lg-9">
					  <input type="text" size="64" name="url_name_{$language['id_lang']}" value="{if isset($values[$language['id_lang']]) && isset($values[$language['id_lang']]['url_name'])}{$values[$language['id_lang']]['url_name']|escape:'htmlall':'UTF-8'}{/if}" />
				  </div>
				  <div class="col-lg-2">
					  <button type="button" class="btn btn-default dropdown-toggle" tabindex="-1" data-toggle="dropdown">
						  {$language['iso_code']}
						  <span class="caret"></span>
					  </button>
					  <ul class="dropdown-menu">
						  {foreach $languages as $language}
						    <li><a href="javascript:hideOtherLanguage({$language['id_lang']});" tabindex="-1">{$language['name']}</a></li>
						  {/foreach}
					  </ul>
				  </div>
			  </div>
			{/foreach}
			<div class="col-lg-9">
				<p class="help-block">{l s='When the Faceted Search module is enabled, you can get more detailed URLs by choosing the word that best represent this feature\'s value. By default, PrestaShop uses the value\'s name, but you can change that setting using this field.' d='Modules.Facetedsearch.Admin'}</p>
			</div>
		</div>
	</div>
</div>
<div class="form-group">
	<label class="control-label col-lg-3">{l s='Meta title' d='Admin.Global'}</label>
	<div class="col-lg-9">
		<div class="row">
			{foreach $languages as $language}
			  <div class="translatable-field lang-{$language['id_lang']}" style="display: {if $language['id_lang'] == $default_form_language}block{else}none{/if};">
				  <div class="col-lg-9">
					  <input type="text" size="70" name="meta_title_{$language['id_lang']}" value="{if isset($values[$language['id_lang']]) && isset($values[$language['id_lang']]['meta_title'])}{$values[$language['id_lang']]['meta_title']|escape:'htmlall':'UTF-8'}{/if}" />
				  </div>
				  <div class="col-lg-2">
					  <button type="button" class="btn btn-default dropdown-toggle" tabindex="-1" data-toggle="dropdown">
						  {$language['iso_code']}
						  <span class="caret"></span>
					  </button>
					  <ul class="dropdown-menu">
						  {foreach $languages as $language}
						    <li><a href="javascript:hideOtherLanguage({$language['id_lang']});" tabindex="-1">{$language['name']}</a></li>
						  {/foreach}
					  </ul>
				  </div>
			  </div>
			{/foreach}
			<div class="col-lg-9">
				<p class="help-block">{l s='When the Faceted Search module is enabled, you can get more detailed page titles by choosing the word that best represent this feature\'s value. By default, PrestaShop uses the value\'s name, but you can change that setting using this field.' d='Modules.Facetedsearch.Admin'}</p>
			</div>
		</div>
	</div>
</div>
