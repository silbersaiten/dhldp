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
	<div class="dhl-products">
		<input type="hidden" name="dhl_products" value="">
		<table class="table">
			<tr>
				<td>{l s='Product' mod='dhldp'}</td>
				<td>{l s='Participation' mod='dhldp'}</td>
				{*				<td>{l s='GoGreen' mod='dhldp'}</td>*}
				<td>{l s='Action' mod='dhldp'}</td>
			<tr>
			<tr id="add-dhl-product">
				<td>
					<select name="product" id="dhl-product-name">
					</select>
				</td>
				<td>
					<input type="text" name="participation" size="2" maxlength="2" id="dhl-product-participation">
				</td>
				{*				<td>*}
				{*					<select name="gogreen" id="dhl-product-gogreen">*}
				{*					</select>*}
				{*				</td>*}
				<td><button name="addDhlProduct" id="addDhlProduct" class="btn btn-default">{l s='Add' mod='dhldp'}</button></td>
			<tr>
				{foreach $added_dhl_products as $added_dhl_product}
			<tr>
				<td><input type="hidden" class="added_dhl_products" name="added_dhl_products[]" value="{$added_dhl_product.code|escape:'htmlall':'UTF-8'}:{$added_dhl_product.part|escape:'htmlall':'UTF-8'}">{$added_dhl_product.name|escape:'htmlall':'UTF-8'}</td>
				<td>{$added_dhl_product.part|escape:'htmlall':'UTF-8'}</td>
				{*				<td>{$added_dhl_product.gogreen|escape:'htmlall':'UTF-8'}</td>*}
				<td><input type="button" name="removeDhlProduct" id="removeDhlProduct" class="btn btn-default" value="{l s='Remove' mod='dhldp'}"/></td>
			</tr>
			{/foreach}
		</table>
	</div>
</div>
