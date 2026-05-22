{**
* DHL Deutschepost
*
* @author    silbersaiten <info@silbersaiten.de>
* @copyright 2023 silbersaiten
* @license   See joined file licence.txt
* @category  Module
* @support   silbersaiten <support@silbersaiten.de>
* @version   1.1.5
* @link      http://www.silbersaiten.de
*}
<div class="row" id="dhldp_dhl_addit_services" style="{if !isset($addit_services.show_dhl_additional_services) || (isset($addit_services.show_dhl_additional_services) && $addit_services.show_dhl_additional_services != 1)}display: none;{/if}">
    <div class="panel{if $is177} card{/if}">
        <div class="panel-heading{if $is177} card-header{/if}">

        {*
            <button type="button" id="collapseDHLDPDhlAdditServices" class="button btn btn-default btn-sm collapseDHLDPDhlAdditServices">{if $is177}<i class="material-icons">expand_less</i>{else}<i class="icon-angle-double-up"></i>{/if} </button> 
        *}

        {l s='Additional services' mod='dhldp'}</div>
        <div class="row form-wrapper form-horizontal">
            <div class="col-lg-6">
                <div class="form-group-flex">
                    <label class="control-label col-lg-6">{l s='Day of delivery' mod='dhldp'}
                        <span class="help-box{if $is16}-f{/if}" data-toggle="popover" data-content="{l s='yyyy-mm-dd' mod='dhldp'}"></span>
                    </label>
                    <div class="col-lg-6">
                        {if $is177}<div class="input-group datepicker">{/if}
                            <input class="form-control{if $is177} custom-select{else} datepicker{/if}" type="text" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][DayOfDelivery]"
                                   value="{$addit_services.DayOfDelivery|escape:'htmlall':'UTF-8'}" maxlength="10"/>
                            {if $is177}</div>{/if}
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-6">{l s='Delivery timeframe' mod='dhldp'}</label>
                    <div class="col-lg-6">
                        <select name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][DeliveryTimeframe]"  class="form-control">
                            <option value="">----</option>
                            {foreach from=$addit_services.deliverytimeframe_options key=option_key item=option_value}
                                <option value="{$option_key|escape:'html':'UTF-8'}"{if $addit_services.DeliveryTimeframe == $option_key} selected="selected"{/if}>{$option_value|escape:'html':'UTF-8'}</option>
                            {/foreach}
                        </select>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-6">{l s='Return immediately' mod='dhldp'}
                        <span class="help-box{if $is16}-f{/if}" data-toggle="popover" data-content="{l s='Service of immediatly shipment return in case of non successful delivery for product' mod='dhldp'}"></span>
                    </label>
                    <div class="col-lg-6">
                        <input class="form-control" type="checkbox" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][ReturnImmediately]"
                                {if $addit_services.ReturnImmediately == 1} checked="checked"{/if} value="1"/>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-6">{l s='Individual sender requirements for product' mod='dhldp'}</label>
                    <div class="col-lg-6">
                        <textarea class="form-control" style="width: 100%;word-wrap: break-word; resize: none; height: 85px;" type="text" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][IndividualSenderRequirement]" maxlength="250">{$addit_services.IndividualSenderRequirement|escape:'htmlall':'UTF-8'}</textarea>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-6">{l s='Service for package return' mod='dhldp'}
                        <span class="help-box{if $is16}-f{/if}" data-toggle="popover" data-content="{l s='After the delivery of DHL Parcel same day, we provide the return of your reusable parcel at your return address. Individual extra charge in addition to the price of a parcel.' mod='dhldp'}"></span>
                    </label>
                    <div class="col-lg-6">
                        <input class="form-control" type="checkbox" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][PackagingReturn]"
                                {if $addit_services.PackagingReturn == 1} checked="checked"{/if} value="1"/>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-6">{l s='Notice Of Non Deliverability' mod='dhldp'}</label>
                    <div class="col-lg-6">
                        <input class="form-control" type="checkbox" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][NoticeOfNonDeliverability]"
                                {if $addit_services.NoticeOfNonDeliverability == 1} checked="checked"{/if} value="1"/>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-6">{l s='Shipment handling for product' mod='dhldp'}</label>
                    <div class="col-lg-6">
                        <select name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][ShipmentHandling]"  class="fixed-width-xl form-control">
                            <option value="">----</option>
                            {foreach from=$addit_services.shipmenthandling_options key=option_key item=option_value}
                                <option value="{$option_key|escape:'html':'UTF-8'}"{if $addit_services.ShipmentHandling == $option_key} selected="selected"{/if}>{$option_value|escape:'html':'UTF-8'}</option>
                            {/foreach}
                        </select>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-6">{l s='Endorsement' mod='dhldp'}</label>
                    <div class="col-lg-6">
                        <select name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][Endorsement]"  class="form-control">
                            {foreach from=$addit_services.endorsement_options key=option_key item=option_value}
                                <option value="{$option_key|escape:'html':'UTF-8'}"{if $addit_services.Endorsement == $option_key} selected="selected"{/if}>{$option_value|escape:'html':'UTF-8'}</option>
                            {/foreach}
                        </select>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-6">{l s='Visual age check' mod='dhldp'}
                        <span class="help-box{if $is16}-f{/if}" data-toggle="popover" data-content="{l s='With the service visual check of age you can order in an uncomplicated and convenient way that your items are not handed over to children or minors. Extra charge in addition to the price of a parcel.' mod='dhldp'}"></span>
                    </label>
                    <div class="col-lg-6">
                        <select name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][VisualCheckOfAge]"  class="form-control">
                            <option value="">----</option>
                            {foreach from=$addit_services.visualcheckofage_options key=option_key item=option_value}
                                <option value="{$option_key|escape:'html':'UTF-8'}"{if $addit_services.VisualCheckOfAge == $option_key} selected="selected"{/if}>{$option_value|escape:'html':'UTF-8'}</option>
                            {/foreach}
                        </select>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-6">{l s='Cash On Delivery' mod='dhldp'}
                        <span class="help-box{if $is16}-f{/if}" data-toggle="popover" data-content="{l s='Safety especially in regard to customers who placed their first order and discretion of payment handling - transaction will not show up in bank account statement of the recipient - enabled by reliable payment during parcel handing over. Extra charge in addition to the price of a parcel.' mod='dhldp'}"></span>
                    </label>
                    <div class="col-lg-6">
                        <input class="form-control" type="checkbox" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][CashOnDelivery]"
                                {if $addit_services.CashOnDelivery == 1} checked="checked"{/if} value="1"/>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-6">{l s='Cash On Delivery: add fee' mod='dhldp'}</label>
                    <div class="col-lg-6">
                        <input class="form-control" type="checkbox" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][CashOnDelivery_addFee]"
                                {if $addit_services.CashOnDelivery_addFee == 1} checked="checked"{/if} value="1"/>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-6">{l s='Cash On Delivery: amount' mod='dhldp'}</label>
                    <div class="col-lg-6">
                        <div class="input-group fixed-width-x2">
                            <input class="form-control" type="text" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][CashOnDelivery_codAmount]"
                                   value="{$addit_services.CashOnDelivery_codAmount|escape:'htmlall':'UTF-8'}" maxlength="10"/>
                            <div class="input-group-addon">{l s='euro' mod='dhldp'} </div>
                        </div>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-6">{l s='Bank data: note' mod='dhldp'}</label>
                    <div class="col-lg-6">
                        <div class="input-group money-type">
                            <input class="form-control" type="text" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][CashOnDelivery_bankdatanote]"
                                   value="{$addit_services.CashOnDelivery_bankdatanote|escape:'htmlall':'UTF-8'}" maxlength="10"/>
                        </div>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-6">{l s='Bank data: note 2' mod='dhldp'}</label>
                    <div class="col-lg-6">
                        <div class="input-group money-type">
                            <input class="form-control" type="text" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][CashOnDelivery_bankdatanote2]"
                                   value="{$addit_services.CashOnDelivery_bankdatanote2|escape:'htmlall':'UTF-8'}" maxlength="10"/>
                        </div>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-6">{l s='Additional Insurance' mod='dhldp'}
                        <span class="help-box{if $is16}-f{/if}" data-toggle="popover" data-content="{l s='The service provides that your parcels are protected, even if they exceed the liability limit of  EUR 500. The additional insurance service offers you sufficient protection up to EUR 2,500 (additional insurance A) or EUR 25,000 (additional insurance B). Therefore you can avoid financial loss or damage. Extra charge in addition to the price of a parcel.' mod='dhldp'}"></span>
                    </label>
                    <div class="col-lg-6">
                        <input class="form-control" type="checkbox" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][AdditionalInsurance]"
                                {if $addit_services.AdditionalInsurance == 1} checked="checked"{/if} value="1"/>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-6">{l s='Additional Insurance: amount' mod='dhldp'}</label>
                    <div class="col-lg-6">
                        <div class="input-group fixed-width-x2">
                            <input class="form-control" type="text" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][AdditionalInsurance_insuranceAmount]"
                                   value="{$addit_services.AdditionalInsurance_insuranceAmount|escape:'htmlall':'UTF-8'}" maxlength="10"/>
                            <div class="input-group-addon">{l s='euro' mod='dhldp'} </div>
                        </div>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-6">{l s='Bulky Goods' mod='dhldp'}</label>
                    <div class="col-lg-6">
                        <input class="form-control" type="checkbox" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][BulkyGoods]"
                                {if $addit_services.BulkyGoods == 1} checked="checked"{/if} value="1"/>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-6">{l s='Signed For By Recipient' mod='dhldp'}
                        <span class="help-box{if $is16}-f{/if}" data-toggle="popover" data-content="{l s='Delivery is signed for by the recipient instead of the DHL driver' mod='dhldp'}"></span>
                    </label>
                    <div class="col-lg-6">
                        <input class="form-control" type="checkbox" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][SignedForByRecipient]"
                                {if $addit_services.SignedForByRecipient == 1} checked="checked"{/if} value="1"/>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-6">{l s='Ident check' mod='dhldp'}
                        <span class="help-box{if $is16}-f{/if}" data-toggle="popover" data-content="{l s='With the service Ident-Check you order that your parcels are only delivered to the recipient in person after the identity and - where applicable - the age have been checked against the identity card. Extra charge in addition to the price of a parcel.' mod='dhldp'}"></span>
                    </label>
                    <div class="col-lg-6">
                        <input class="form-control" type="checkbox" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][IdentCheck]"
                                {if $addit_services.IdentCheck == 1} checked="checked"{/if} value="1"/>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-6">{l s='Ident check: surname' mod='dhldp'}</label>
                    <div class="col-lg-6">
                            <textarea class="form-control" style="width: 100%;word-wrap: break-word; resize: none; height: 85px;" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][IdentCheck_Ident_surname]"
                                      maxlength="255">{$addit_services.IdentCheck_Ident_surname|escape:'htmlall':'UTF-8'}</textarea>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-6">{l s='Ident check: first name' mod='dhldp'}</label>
                    <div class="col-lg-6">
                            <textarea class="form-control" style="width: 100%;word-wrap: break-word; resize: none; height: 85px;" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][IdentCheck_Ident_givenName]"
                                      maxlength="255">{$addit_services.IdentCheck_Ident_givenName|escape:'htmlall':'UTF-8'}</textarea>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-6">{l s='Ident check: date of birth' mod='dhldp'}</label>
                    <div class="row">
                        {if $is177}
                        <div class="input-group datepicker">
                            {/if}
                            <input class="form-control{if $is177} custom-select{else} datepicker{/if}"
                                   type="text"
                                   name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][IdentCheck_Ident_dateOfBirth]"
                                   value="{if isset($smarty.post.addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}]['IdentCheck_Ident_dateOfBirth'])}
                                                {$smarty.post.addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}]['IdentCheck_Ident_dateOfBirth']|escape:'htmlall':'UTF-8'}
                                                    {else}{$addit_services.IdentCheck_Ident_dateOfBirth|escape:'htmlall':'UTF-8'}
                                                {/if}"
                                   maxlength="10" />
                            {if $is177}
                        </div>
                        {/if}
                    </div>

                    {*                        <input class="form-control datepicker" type="date" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][IdentCheck_Ident_dateOfBirth]"*}
                    {*                               value="{$addit_services.IdentCheck_Ident_dateOfBirth|escape:'htmlall':'UTF-8'}" maxlength="10"/>*}
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-6">{l s='Ident check: minimum age' mod='dhldp'}</label>
                    <div class="col-lg-6">
                        <select name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][IdentCheck_Ident_minimumAge]" class="form-control">
                            <option value="">----</option>
                            {foreach from=$addit_services.minimum_age_options key=option_key item=option_value}
                                <option value="{$option_key|escape:'html':'UTF-8'}"{if $addit_services.IdentCheck_Ident_minimumAge == $option_key} selected="selected"{/if}>
                                    {$option_value|escape:'html':'UTF-8'}
                                </option>
                            {/foreach}
                        </select>
                    </div>
                    {*                        <input class="form-control" type="text" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][IdentCheck_Ident_minimumAge]"*}
                    {*                               value="{$addit_services.IdentCheck_Ident_minimumAge|escape:'htmlall':'UTF-8'}" maxlength="3"/>*}
                </div>
                {*                    <div class="form-group-flex">*}
                {*                        <label class="control-label col-lg-6">{l s='GoGreen' mod='dhldp'}*}
                {*                            <span class="help-box{if $is16}-f{/if}" data-toggle="popover" data-content="{l s='You will make a sustainable contribution towards climate protection by offsetting the CO2 e-emissions generated during the transportation of your items. Extra charge in addition to the price of a parcel.' mod='dhldp'}"></span>*}
                {*                        </label>*}
                {*                        <div class="col-lg-6">*}
                {*                            <input class="form-control" type="checkbox" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][GoGreen]"*}
                {*                                    {if $addit_services.GoGreen == 1} checked="checked"{/if} value="1"/>*}
                {*                        </div>*}
                {*                    </div>*}
                <div class="form-group-flex">
                    <label class="control-label col-lg-6">{l s='Perishables' mod='dhldp'}</label>
                    <div class="col-lg-6">
                        <input class="form-control" type="checkbox" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][Perishables]"
                                {if $addit_services.Perishables == 1} checked="checked"{/if} value="1"/>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="form-group-flex">
                    <label class="control-label col-lg-6">{l s='Personally' mod='dhldp'}</label>
                    <div class="col-lg-6">
                        <input class="form-control" type="checkbox" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][Personally]"
                                {if $addit_services.Personally == 1} checked="checked"{/if} value="1"/>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-6">{l s='No Neighbour Delivery' mod='dhldp'}
                        <span class="help-box{if $is16}-f{/if}" data-toggle="popover" data-content="{l s='With the service no neighbour delivery you can exclude an alternative delivery to the neighbour in case the recipient is not available during delivery. Extra charge in addition to the price of a parcel.' mod='dhldp'}"></span>
                    </label>
                    <div class="col-lg-6">
                        <input class="form-control" type="checkbox" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][NoNeighbourDelivery]"
                                {if $addit_services.NoNeighbourDelivery == 1} checked="checked"{/if} value="1"/>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-6">{l s='Notice Of Non Deliverability' mod='dhldp'}
                        <span class="help-box{if $is16}-f{/if}" data-toggle="popover" data-content="{l s='Delivery can only be signed for by yourself personally or by members of your household.' mod='dhldp'}"></span>
                    </label>
                    <div class="col-lg-6">
                        <input class="form-control" type="checkbox" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][NoticeOfNonDeliverability]"
                                {if $addit_services.NoticeOfNonDeliverability == 1} checked="checked"{/if} value="1"/>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-6">{l s='Named Person Only' mod='dhldp'}
                        <span class="help-box{if $is16}-f{/if}" data-toggle="popover" data-content="{l s='With the service named person only you order that your parcels are only delivered to the recipient in person or to an authorized person. Extra charge in addition to the price of a parcel.' mod='dhldp'}"></span>
                    </label>
                    <div class="col-lg-6">
                        <input class="form-control" type="checkbox" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][NamedPersonOnly]"
                                {if $addit_services.NamedPersonOnly == 1} checked="checked"{/if} value="1"/>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-6">{l s='Return receipt' mod='dhldp'}</label>
                    <div class="col-lg-6">
                        <input class="form-control" type="checkbox" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][ReturnReceipt]"
                                {if $addit_services.ReturnReceipt == 1} checked="checked"{/if} value="1"/>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-6">{l s='Premium' mod='dhldp'}</label>
                    <div class="col-lg-6">
                        <input class="form-control" type="checkbox" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][Premium]"
                                {if $addit_services.Premium == 1} checked="checked"{/if} value="1"/>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-6">{l s='Drop-off location' mod='dhldp'}
                        <span class="help-box{if $is16}-f{/if}" data-toggle="popover" data-content="{l s='This addressing version enables your customers to receive parcels round the clock, even if nobody sits at home. No extra charge.' mod='dhldp'}"></span>
                    </label>
                    <div class="col-lg-6">
                            <textarea class="form-control" style="width: 100%;word-wrap: break-word; resize: none; height: 85px;" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][PreferredLocation]"
                                      maxlength="100">{$addit_services.PreferredLocation|escape:'htmlall':'UTF-8'}</textarea>
                        <span class="help-block"></span>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-6">{l s='Neighbour' mod='dhldp'}
                        <span class="help-box{if $is16}-f{/if}" data-toggle="popover" data-content="{l s=' If the customer is not at home on the day of delivery, the parcels are left with the preferred neighbour. No extra charge.' mod='dhldp'}"></span>
                    </label>
                    <div class="col-lg-6">
                            <textarea class="form-control" style="width: 100%;word-wrap: break-word; resize: none; height: 85px;" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][PreferredNeighbour]"
                                      maxlength="100">{$addit_services.PreferredNeighbour|escape:'htmlall':'UTF-8'}</textarea>
                        <span class="help-block"></span>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-6">{l s='Delivery day' mod='dhldp'}
                        <span class="help-box{if $is16}-f{/if}" data-toggle="popover" data-content="{l s='Preferred day of delivery in format YYYY-MM-DD. Shipper can request a preferred day of delivery. The preferred day should be between 2 and 6 working days after handover to DHL.' mod='dhldp'}"></span>
                    </label>
                    <div class="col-lg-6">
                        <div class="row">
                            {if $is177}<div class="input-group datepicker">{/if}
                                <input class="form-control{if $is177} custom-select{else} datepicker{/if}"
                                       type="text"
                                       name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][PreferredDay]"
                                       value="{if isset($smarty.post.addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}]['PreferredDay'])}
                                                            {$smarty.post.addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}]['PreferredDay']|escape:'htmlall':'UTF-8'}
                                                        {else}{$addit_services.PreferredDay|escape:'htmlall':'UTF-8'}
                                                   {/if}"
                                       maxlength="10" />
                                {if $is177}</div>{/if}
                            {*                                <span class="help-block">{l s='yyyy-mm-dd' mod='dhldp'}</span>*}
                        </div>
                        {*                            <textarea class="form-control" style="width: 100%;word-wrap: break-word; resize: none; height: 85px;" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][PreferredDay]"*}
                        {*                                      maxlength="100">{$addit_services.PreferredDay|escape:'htmlall':'UTF-8'}</textarea>*}
                        {*                            <span class="help-block"></span>*}
                    </div>
                </div>
                {*<div class="form-group-flex">
                    <label class="control-label col-lg-6">{l s='Preferred time' mod='dhldp'}</label>
                    <div class="col-lg-6">
                        <select name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][PreferredTime]"  class="form-control">
                            <option value="">----</option>
                            {foreach from=$addit_services.preferredtime_options key=option_key item=option_value}
                                <option value="{$option_key|escape:'html':'UTF-8'}"{if $addit_services.PreferredTime == $option_key} selected="selected"{/if}>{$option_value|escape:'html':'UTF-8'}</option>
                            {/foreach}
                        </select>
                        <span class="help-block">{l s='With the service preferred time your customers can select one of the two-hour time frames for the delivery of your items – nationwide in Germany. Extra charge in addition to the price of a parcel.' mod='dhldp'}</span>
                    </div>
                </div>*}
            </div>
        </div>
        <input type="hidden" id="show_dhl_additional_services" name="addit_services[{$addit_services.id_order_carrier|escape:'htmlall':'UTF-8'}][show_dhl_additional_services]" value="{$addit_services.show_dhl_additional_services|escape:'htmlall':'UTF-8'}">
    </div>
</div>
<script type="text/javascript">
    $(function() {
        if (!is177) {
            $(".datepicker").datepicker({
                prevText: '',
                nextText: '',
                dateFormat: 'yy-mm-dd'
            });
        }

        $("textarea").maxlength(
            {
                text: '%length / %maxlength'
            }
        );

        if ($("select[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][exportType]']").val() == 'OTHER') {
            $("textarea[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][exportTypeDescription]']").closest('div.form-group-flex').show()
        } else {
            $("textarea[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][exportTypeDescription]']").closest('div.form-group-flex').hide()
        }

        if ($("input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][CashOnDelivery]']:checked").length) {
            $("input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][CashOnDelivery_addFee]']").closest('div.form-group-flex').show();
            $("input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][CashOnDelivery_codAmount]']").closest('div.form-group-flex').show();
            $("input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][CashOnDelivery_bankdatanote]']").closest('div.form-group-flex').show();
            $("input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][CashOnDelivery_bankdatanote2]']").closest('div.form-group-flex').show();
        } else {
            $("input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][CashOnDelivery_addFee]']").closest('div.form-group-flex').hide();
            $("input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][CashOnDelivery_codAmount]']").closest('div.form-group-flex').hide();
            $("input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][CashOnDelivery_bankdatanote]']").closest('div.form-group-flex').hide();
            $("input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][CashOnDelivery_bankdatanote2]']").closest('div.form-group-flex').hide();
        }

        if ($("input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][AdditionalInsurance]']:checked").length) {
            $("input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][AdditionalInsurance_insuranceAmount]']").closest('div.form-group-flex').show();
        } else {
            $("input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][AdditionalInsurance_insuranceAmount]']").closest('div.form-group-flex').hide();
        }

        if ($("input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][ParcelOutletRouting]']:checked:visible").length) {
            $("input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][ParcelOutletRouting_details]']").closest('div.form-group-flex').show();
        } else {
            $("input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][ParcelOutletRouting_details]']").closest('div.form-group-flex').hide();
        }

        if ($("input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][IdentCheck]']:checked").length) {
            $("textarea[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][IdentCheck_Ident_surname]']").closest('div.form-group-flex').show();
            $("textarea[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][IdentCheck_Ident_givenName]']").closest('div.form-group-flex').show();
            $("input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][IdentCheck_Ident_dateOfBirth]']").closest('div.form-group-flex').show();
            $("select[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][IdentCheck_Ident_minimumAge]']").closest('div.form-group-flex').show();
        } else {
            $("textarea[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][IdentCheck_Ident_surname]']").closest('div.form-group-flex').hide();
            $("textarea[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][IdentCheck_Ident_givenName]']").closest('div.form-group-flex').hide();
            $("input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][IdentCheck_Ident_dateOfBirth]']").closest('div.form-group-flex').hide();
            $("select[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][IdentCheck_Ident_minimumAge]']").closest('div.form-group-flex').hide();
        }


        $(document).on('click', "input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][IdentCheck]']", function() {
            if ($(this).is(':checked')) {
                $("textarea[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][IdentCheck_Ident_surname]']").closest('div.form-group-flex').show();
                $("textarea[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][IdentCheck_Ident_givenName]']").closest('div.form-group-flex').show();
                $("input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][IdentCheck_Ident_dateOfBirth]']").closest('div.form-group-flex').show();
                $("select[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][IdentCheck_Ident_minimumAge]']").closest('div.form-group-flex').show();
            } else {
                $("textarea[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][IdentCheck_Ident_surname]']").closest('div.form-group-flex').hide();
                $("textarea[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][IdentCheck_Ident_givenName]']").closest('div.form-group-flex').hide();
                $("input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][IdentCheck_Ident_dateOfBirth]']").closest('div.form-group-flex').hide();
                $("select[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][IdentCheck_Ident_minimumAge]']").closest('div.form-group-flex').hide();
            }
        });

        $(document).on('click', "input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][AdditionalInsurance]']", function() {
            if ($(this).is(':checked')) {
                $("input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][AdditionalInsurance_insuranceAmount]']").closest('div.form-group-flex').show();
            } else {
                $("input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][AdditionalInsurance_insuranceAmount]']").closest('div.form-group-flex').hide();
            }
        });

        $(document).on('click', "input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][ParcelOutletRouting]']", function() {
            if ($(this).is(':checked')) {
                $("input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][ParcelOutletRouting_details]']").closest('div.form-group-flex').show();
            } else {
                $("input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][ParcelOutletRouting_details]']").closest('div.form-group-flex').hide();
            }
        });

        $(document).on('click', "input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][CashOnDelivery]']", function() {
            if ($(this).is(':checked')) {
                $("input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][CashOnDelivery_addFee]']").closest('div.form-group-flex').show();
                $("input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][CashOnDelivery_codAmount]']").closest('div.form-group-flex').show();
                $("input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][CashOnDelivery_bankdatanote]']").closest('div.form-group-flex').show();
                $("input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][CashOnDelivery_bankdatanote2]']").closest('div.form-group-flex').show();
            } else {
                $("input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][CashOnDelivery_addFee]']").closest('div.form-group-flex').hide();
                $("input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][CashOnDelivery_codAmount]']").closest('div.form-group-flex').hide();
                $("input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][CashOnDelivery_bankdatanote]']").closest('div.form-group-flex').hide();
                $("input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][CashOnDelivery_bankdatanote2]']").closest('div.form-group-flex').hide();
            }
        });

        $(document).on('click', "input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][Notification]']", function() {
            if ($(this).is(':checked')) {
                $("input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][Notification_recepientEmailAddress]']").closest('div.form-group-flex').show();
            } else {
                $("input[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][Notification_recepientEmailAddress]']").closest('div.form-group-flex').hide();
            }
        });

        $(document).on('change', "select[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][exportType]']", function() {
            if ($("select[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][exportType]']").val() == 'OTHER') {
                $("textarea[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][exportTypeDescription]']").closest('div.form-group-flex').show()
            } else {
                $("textarea[name*='[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][exportTypeDescription]']").closest('div.form-group-flex').hide()
            }
        })
    });
</script>