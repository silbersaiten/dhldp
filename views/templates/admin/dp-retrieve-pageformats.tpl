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
<select name="DHLDP_DP_PAGE_FORMAT" class="fixed-width-xl" style="display: inline-block;">
    {foreach from=$page_formats key=page_format_key item=page_format_item}
        <option value="{$page_format_key|escape:'htmlall':'UTF-8'}"{if isset($page_format) && $page_format == $page_format_key}selected{/if}>{$page_format_item.name|escape:'htmlall':'UTF-8'} ; {$page_format_item.type|escape:'htmlall':'UTF-8'} ; {$page_format_item.orie|escape:'htmlall':'UTF-8'}</option>
    {/foreach}
</select>
<button class="btn btn-primary" name="submitDPRetrievePageFormats">{l s='Retrieve page formats' mod='dhldp'}</button>