{**
* DHL Deutschepost
*
* @author    silbersaiten <info@silbersaiten.de>
* @copyright 2023 silbersaiten
* @license   See joined file licence.txt
* @category  Module
* @support   silbersaiten <support@silbersaiten.de>
* @version   2.0.0
* @link      http://www.silbersaiten.de
*}
<div class="container dhl_pfps" id="dhl_pfps" style="display:none;">
    <div class="row">
        <h4 class="page-heading dhl_pfps_title" id="dhl_pfps_title_ps">{l s='Choose a DHL Packstation' mod='dhldp'}</h4>
        <h4 class="page-heading dhl_pfps_title" id="dhl_pfps_title_pf">{l s='Choose a DHL Postfiliale' mod='dhldp'}</h4>
    </div>
    <div class="row dhl_search">
        <div id="errors" class="form-group alert alert-danger"></div>
        <div class="form-group col-md-2">
            <label for="dhl_pfps_zip">{l s='Zip' mod='dhldp'}<sup>*</sup></label>
            <input id="dhl_pfps_zip" type="text" class="form-control is_required" data-validate="isGenericName" name="dhl_pfps_zip">
        </div>
        <div class="form-group col-md-3">
            <label for="dhl_pfps_city">{l s='City' mod='dhldp'}<sup>**</sup></label>
            <input id="dhl_pfps_city" type="text" class="form-control is_required" data-validate="isGenericName" name="dhl_pfps_city">
        </div>
        <div class="form-group col-md-3">
            <label for="dhl_pfps_street">{l s='Street' mod='dhldp'}</label>
            <input id="dhl_pfps_street" type="text" class="form-control is_required" data-validate="isGenericName" name="dhl_pfps_street">
        </div>
        <div class="form-group col-md-2">
            <label for="dhl_pfps_street_no">{l s='House number' mod='dhldp'}</label>
            <input id="dhl_pfps_street_no" type="text" class="form-control is_required" data-validate="isGenericName" name="dhl_pfps_street_no">
            <input type="hidden" class="form-control" name="dhl_pfps_type">
            <input type="hidden" class="form-control" name="dhl_pfps_number">
        </div>
        <div class="form-group col-md-2">
            <button type="button" id="findPFPS" class="button btn btn-default button-small mt-2"><span>{l s='Search' mod='dhldp'} <i class="icon-search"></i></span></button>
        </div> 
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="dhl_list"></div>
        </div>
        <div class="map_wrapper col-md-6">
            <div id="map"></div>
        </div>
    </div>
</div>