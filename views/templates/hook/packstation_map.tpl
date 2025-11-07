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
<div class="row dhl_q" style="display:none">
	<div class="col-xs-9 col-sm-9 col-md-9">
		<button type="button" class="button btn btn-default button-medium findShopMap"><span>{l s='Find Shop' mod='dhldp'}</span></button>
	</div>
</div>
<div class="row dhl_map" style="">
	<div class="col-xs-9 col-sm-9 col-md-9">
		<div id="map" style=""></div>
	</div>
	<div class="col-xs-3 col-sm-3 col-md-3">
		<div class="form-group">
			<label for="dhl_zip">{l s='Zip/Postal code' mod='dhldp'}<sup>*</sup></label>
			<input type="text" id="dhl_zip" class="form-control is_required" data-validate="isGenericName" name="dhldp_zip">
			<input type="hidden" class="form-control is_required dhl_postbox" data-validate="isGenericName" name="dhldp_packstation" value="">
		</div>
		{*<div class="form-group">
			<label for="id_country">{l s='Country' mod='dhldp'}<sup>*</sup></label>
			<select id="id_country" class="form-control" name="dhldp_country">
				{foreach from=$country_list item=country}
				<option value="{$country.iso_code|escape:'htmlall':'UTF-8'}">{$country.name|escape:'htmlall':'UTF-8'}</option>
				{/foreach}
			</select>
		</div>*}
		<button type="button" class="button btn btn-default button-medium findShop"><span>{l s='Search' mod='dhldp'}</span></button>
		<div id="dhl-select-shop-info">

		</div>
	</div>
</div>

{strip}
{addJsDef map=''}
{addJsDef infoWindow=''}
{addJsDef path=$path}
{addJsDef carriers=$carriers}
{addJsDef markers=array()}
{addJsDef defaultLat=$defaultLat}
{addJsDef defaultLong=$defaultLong}
{/strip}
