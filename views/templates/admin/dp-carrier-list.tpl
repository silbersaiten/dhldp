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
	<div class="list-carriers">
		<div class="col-xs-5 col-sm-5 col-md-5">
				{foreach $carriers as $carrier}
				<input id="hc_{$carrier['id_carrier']|escape:'htmlall':'UTF-8'}" type="checkbox" name="deutschepost_carriers[]" value="{$carrier['id_carrier']|escape:'htmlall':'UTF-8'}"
				{if is_array($dp_carriers) && in_array($carrier['id_carrier'], $dp_carriers)} checked {/if}> <label for="hc_{$carrier['id_carrier']|escape:'htmlall':'UTF-8'}"> {$carrier['name']|escape:'htmlall':'UTF-8'} </label><br>
				{/foreach}
		</div>
	</div>
</div>
