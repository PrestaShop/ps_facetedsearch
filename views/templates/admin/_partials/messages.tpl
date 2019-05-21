{**
  * 2007-2019 PrestaShop.
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
  * DISCLAIMER
  *
  * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
  * versions in the future. If you wish to customize PrestaShop for your
  * needs please refer to http://www.prestashop.com for more information.
  *
  * @author    PrestaShop SA <contact@prestashop.com>
  * @copyright 2007-2019 PrestaShop SA
  * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
  * International Registered Trademark & Property of PrestaShop SA
  *}
{if isset($message)}{$message}{/if}

<div id="ajax-message-ok" class="conf ajax-message alert alert-success" style="display: none">
	<span class="message"></span>
</div>
<div id="ajax-message-ko" class="error ajax-message alert alert-danger" style="display: none">
	<span class="message"></span>
</div>

{if !empty($limit_warning)}
	<div class="alert alert-danger">
		{if $limit_warning['error_type'] == 'suhosin'}
			{l s='Warning! Your hosting provider is using the Suhosin patch for PHP, which limits the maximum number of fields allowed in a form:' d='Modules.Facetedsearch.Admin'}

			<b>{$limit_warning['post.max_vars']}</b> {l s='for suhosin.post.max_vars.' d='Modules.Facetedsearch.Admin'}<br/>
			<b>{$limit_warning['request.max_vars']}</b> {l s='for suhosin.request.max_vars.' d='Modules.Facetedsearch.Admin'}<br/>
			{l s='Please ask your hosting provider to increase the Suhosin limit to' d='Modules.Facetedsearch.Admin'}
		{else}
			{l s='Warning! Your PHP configuration limits the maximum number of fields allowed in a form:' d='Modules.Facetedsearch.Admin'}<br/>
			<b>{$limit_warning['max_input_vars']}</b> {l s='for max_input_vars.' d='Modules.Facetedsearch.Admin'}<br/>
			{l s='Please ask your hosting provider to increase this limit to' d='Modules.Facetedsearch.Admin'}
		{/if}
		{l s='%s at least, or you will have to edit the translation files manually.' sprintf=$limit_warning['needed_limit'] d='Modules.Facetedsearch.Admin'}
	</div>
{/if}
