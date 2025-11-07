{**
* DHL Deutschepost
*
* @author    silbersaiten <info@silbersaiten.de>
* @copyright 2020 silbersaiten
* @license   See joined file licence.txt
* @category  Module
* @support   silbersaiten <support@silbersaiten.de>
* @version   1.0.0
* @link      http://www.silbersaiten.de
*}
<div class="carrier-list col-xs-10 col-sm-10 col-md-10" style="border: 1px solid #ddd; border-radius: 4px 4px 0 0; padding: 10px;">
	<div class="dhl-list-carriers">
		<table class="table">
			<tr>
				<td>{l s='Carrier' mod='dhldp'}</td>
				<td>{l s='Enable' mod='dhldp'}</td>
				<td>{l s='Default DHL Product' mod='dhldp'}</td>
			<tr>
				{foreach $carriers as $carrier}
			<tr>
				<td>
					<label for="dhlc_{$carrier['id_carrier']|escape:'htmlall':'UTF-8'}"> {$carrier['name']|escape:'htmlall':'UTF-8'} </label>
				</td>
				<td>
					<input class="dhlc" id="dhlc_{$carrier['id_carrier']|escape:'htmlall':'UTF-8'}" type="checkbox" name="dhl_carriers[{$carrier['id_carrier']|escape:'htmlall':'UTF-8'}][carrier]" value="{$carrier['id_carrier']|escape:'htmlall':'UTF-8'}"
							{if is_array($dhl_carriers) && in_array($carrier['id_carrier'], array_keys($dhl_carriers))} checked {/if}>
				</td>
				<td>
					<select class="dhlcp" id="dhlcp_{$carrier['id_carrier']|escape:'htmlall':'UTF-8'}" name="dhl_carriers[{$carrier['id_carrier']|escape:'htmlall':'UTF-8'}][product]">
						<option value=""></option>
						{foreach $added_dhl_products as $added_dhl_product}
							{assign var="item_value" value="{$added_dhl_product.code|escape:'htmlall':'UTF-8'}:{$added_dhl_product.part|escape:'htmlall':'UTF-8'}"}
							<option value="{$item_value|escape:'htmlall':'UTF-8'}"
									{if isset($dhl_carriers[$carrier['id_carrier']]) && isset($dhl_carriers[$carrier['id_carrier']]['product']) && $dhl_carriers[$carrier['id_carrier']]['product'] == $item_value} selected="selected"{/if}
							>{$added_dhl_product.name|escape:'htmlall':'UTF-8'} {$added_dhl_product.part|escape:'htmlall':'UTF-8'}</option>
						{/foreach}
					</select>
				</td>
			</tr>
			{/foreach}
		</table>
	</div>
</div>
