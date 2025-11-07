{**
* DHL Deutschepost
*
* @author    silbersaiten <info@silbersaiten.de>
* @copyright 2025 silbersaiten
* @license   See joined file licence.txt
* @category  Module
* @support   silbersaiten <support@silbersaiten.de>
* @version   3.0.4
* @link      https://www.silbersaiten.de
*}
<div id="address_types" class="required form-group row" style="display: none;">
    <div class="col-md-3"></div>
    <div class="col-md-9">
        <input type="radio" style="margin-right: 5px;" id="address_type_RE" name="address_type" value="RE">
        <label style="margin-right: 10px;" for="address_type_RE">{l s='Regular address' mod='dhldp'}</label>
        <input type="radio" style="margin-right: 5px;" id="address_type_PS" name="address_type" value="PS">
        <label style="margin-right: 10px;" for="address_type_PS">{l s='DHL Packstation' mod='dhldp'}</label>
        <input type="radio" style="margin-right: 5px;" id="address_type_PF" name="address_type" value="PF">
        <label style="margin-right: 10px;" for="address_type_PF">{l s='DHL Postfiliale' mod='dhldp'}</label>
    </div>
</div>
<div class="row dhl_address_warning" style="display: none;">
    <div class="col-md-12 alert alert-warning">
        {l s='You can use this address only for delivery address' mod='dhldp'}
    </div>
</div>
<div id="block_PS" class="required form-group row" style="display: none;">
    <div class="col-md-3"></div>
    <div class="col-md-6">
        <label>{l s='Packstation number' mod='dhldp'} <sup>*</sup></label>
        {if !empty($display_search_button) && $display_search_button == 1}
            <button class="btn btn-default button-search" style="padding: 3px 6px;" type="button" id="searchPS"
                    name="searchPS"><span>{l s='Search' mod='dhldp'}</span></button>
        {/if}
        <input class="form-control is_required" type="text" name="packstation_number"/>
        <label>{l s='Post number' mod='dhldp'} <sup>*</sup></label>
        <input class="form-control is_required" type="text" name="post_number_ps"/>
    </div>
    <div class="col-md-3"></div>
</div>
<div id="block_PF" class="required form-group row" style="display: none;">
    <div class="col-md-3"></div>
    <div class="col-md-6">
        <label>{l s='Postfiliale number' mod='dhldp'} <sup>*</sup></label>
        {if !empty($display_search_button) && $display_search_button == 1}
            <button class="btn btn-default button-search" style="padding: 3px 6px;" type="button" id="searchPF"
                    name="searchPF"><span>{l s='Search' mod='dhldp'}</span></button>
        {/if}
        <input class="form-control is_required" type="text" name="postfiliale_number"/>
        <label>{l s='Post number' mod='dhldp'} <sup>*</sup></label>
        <input class="form-control is_required" type="text" name="post_number_pf"/>
    </div>
    <div class="col-md-3"></div>
</div>
{include file="$self/views/templates/front/map.tpl"}