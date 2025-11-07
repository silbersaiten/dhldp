{**
* DHL Deutschepost
*
* @author    silbersaiten <info@silbersaiten.de>
* @copyright 2025 silbersaiten
* @license   See joined file licence.txt
* @category  Module
* @support   silbersaiten <support@silbersaiten.de>
* @version   3.0.0
* @link      http://www.silbersaiten.de
*}
<div class="carrier-list col-xs-6 col-sm-6 col-md-6" style="border: 1px solid #ddd; border-radius: 4px 4px 0 0; padding: 10px;">
	<div>
		<input type="hidden" name="dhl_product_dimensions" value="">
		<table class="table">
			<tr id="dhl_product_dimension">
				<td colspan="2">
					<select class="dhlcp" id="dhlcp_product_dimension" name="dhl_product_dimension">
						<option value=""></option>
						{if isset($dhl_product_dimensions) && $dhl_product_dimensions}
							{foreach $dhl_product_dimensions as $dhl_product_dimension}
								{assign var="item_value" value="{$dhl_product_dimension.code|escape:'htmlall':'UTF-8'}:{$dhl_product_dimension.part|escape:'htmlall':'UTF-8'}"}
								<option value="{$item_value|escape:'htmlall':'UTF-8'}"
										{if isset($selected_product) && $selected_product == $item_value} selected="selected"{/if}
							 >{$dhl_product_dimension.name|escape:'htmlall':'UTF-8'} {$dhl_product_dimension.part|escape:'htmlall':'UTF-8'}</option>
							{/foreach}
						{/if}
					</select>
				</td>
			</tr>
			<tr>
				<td>{l s='Default length of package' mod='dhldp'}</td>
				<td>
					<input type="text" name="defaultLength" size="2" class="fixed-width-xs" id="defaultLength" value="{if isset($product_dimensions.length)}{$product_dimensions.length|escape:'htmlall':'UTF-8'}{/if}">
				</td>
			</tr>
			<tr>
				<td>{l s='Default width of package' mod='dhldp'}</td>
				<td>
					<input type="text" name="defaultWidth" size="2" class="fixed-width-xs" id="defaultWidth" value="{if isset($product_dimensions.width)}{$product_dimensions.width|escape:'htmlall':'UTF-8'}{/if}">
				</td>
			</tr>
			<tr>
				<td>{l s='Default height of package' mod='dhldp'}</td>
				<td>
					<input type="text" name="defaultHeight" size="2" class="fixed-width-xs" id="defaultHeight" value="{if isset($product_dimensions.height)}{$product_dimensions.height|escape:'htmlall':'UTF-8'}{/if}">
				</td>
			</tr>
			<tr>
				<td>{l s='Default Weight of package' mod='dhldp'}</td>
				<td>
					<input type="text" name="defaultWeight" size="2" class="fixed-width-xs" id="defaultWeight" value="{if isset($product_dimensions.weight)}{$product_dimensions.weight|escape:'htmlall':'UTF-8'}{/if}">
				</td>
			</tr>
			<tfoot>
			<tr>
				<td colspan="2" style="text-align: right;">
					<input type="button" name="cancelDhlProductDimension" id="cancelDhlProductDimension" class="btn btn-cancel" value="{l s='Cancel' mod='dhldp'}"/>
					<input type="button" name="saveDhlProductDimension" id="saveDhlProductDimension" class="btn btn-save" value="{l s='Save' mod='dhldp'}"/>
				</td>
			</tr>
			</tfoot>
		</table>
	</div>
</div>
