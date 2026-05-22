{**
* DHL Deutschepost
*
* @author    silbersaiten <info@silbersaiten.de>
* @copyright 2021 silbersaiten
* @license   See joined file licence.txt
* @category  Module
* @support   silbersaiten <support@silbersaiten.de>
* @version   1.0.13
* @link      http://www.silbersaiten.de
*}
<div class="panel card">
    <div class="row {if $is177}col-lg-12{/if}" id="dhldp_dhl_update_address" style="{if !isset($address.show_update_address) || (isset($address.show_update_address) && $address.show_update_address != 1)}display: none;{/if}">
        <div class="col-lg-4">
            <div class="panel dhldp_dhl_delivery_address">
                <div class="panel-heading{if $is177} card-header{/if}">
                {*
                    <button type="button" id="collapseDHLDPDhlUpdateAddress" class="button btn btn-default btn-sm collapseDHLDPDhlUpdateAddress">
                    {if $is177}<i class="material-icons">expand_less</i>{else}
                    <i class="icon-angle-double-up"></i>{/if} 
                    </button> 
                *}

                {l s='Delivery Address' mod='dhldp'}
                </div>
                <div class="row form-wrapper form-horizontal">
                    <div class="form-group-flex">
                        <label class="control-label col-lg-4">{l s='Company' mod='dhldp'}</label>
                        <div class="form-group-flex-content col-lg-8">{$address.delivery_address->company|escape:'html':'UTF-8'}</div>
                    </div>
                    <div class="form-group-flex">
                        <label class="control-label col-lg-4">{l s='First name' mod='dhldp'}</label>
                        <div class="form-group-flex-content col-lg-8">{$address.delivery_address->firstname|escape:'html':'UTF-8'}</div>
                    </div>
                    <div class="form-group-flex">
                        <label class="control-label col-lg-4">{l s='Last name' mod='dhldp'}</label>
                        <div class="form-group-flex-content col-lg-8">{$address.delivery_address->lastname|escape:'html':'UTF-8'}</div>
                    </div>
                    <div class="form-group-flex">
                        <label class="control-label col-lg-4">{l s='Address' mod='dhldp'}</label>
                        <div class="form-group-flex-content col-lg-8">{$address.delivery_address->address1|escape:'html':'UTF-8'}</div>
                    </div>
                    <div class="form-group-flex">
                        <label class="control-label col-lg-4">{l s='Address (line 2)' mod='dhldp'}</label>
                        <div class="form-group-flex-content col-lg-8">{$address.delivery_address->address2|escape:'html':'UTF-8'}</div>
                    </div>
                    <div class="form-group-flex">
                        <label class="control-label col-lg-4">{l s='Zip/Postal Code' mod='dhldp'}</label>
                        <div class="form-group-flex-content col-lg-8">{$address.delivery_address->postcode|escape:'html':'UTF-8'}</div>
                    </div>
                    <div class="form-group-flex">
                        <label class="control-label col-lg-4">{l s='City' mod='dhldp'}</label>
                        <div class="form-group-flex-content col-lg-8">{$address.delivery_address->city|escape:'html':'UTF-8'}</div>
                    </div>
                    <div class="form-group-flex">
                        <label class="control-label col-lg-4">{l s='Country' mod='dhldp'}</label>
                        <div class="form-group-flex-content col-lg-8">{$address.delivery_country|escape:'html':'UTF-8'}</div>
                    </div>
                    <div class="form-group-flex">
                        <label class="control-label col-lg-4">{l s='State' mod='dhldp'}</label>
                        <div class="form-group-flex-content col-lg-8">{$address.delivery_state|escape:'html':'UTF-8'}</div>
                    </div>
                    <div class="form-group-flex">
                        <label class="control-label col-lg-4">{l s='Home phone' mod='dhldp'}</label>
                        <div class="form-group-flex-content col-lg-8">{$address.delivery_address->phone|escape:'html':'UTF-8'}</div>
                    </div>
                    <div class="form-group-flex">
                        <label class="control-label col-lg-4">{l s='Mobile phone' mod='dhldp'}</label>
                        <div class="form-group-flex-content col-lg-8">{$address.delivery_address->phone_mobile|escape:'html':'UTF-8'}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="panel">
                <div class="panel-heading{if $is177} card-header{/if}">{l s='DHL Receiver Address' mod='dhldp'}</div>
                <div class="row form-wrapper form-horizontal">
                    <div class="col-lg-6">
                        <div>
                            <div class="form-group-flex">
                                <label class="control-label col-lg-4"><sup>*</sup> {l s='Name / Company' mod='dhldp'}</label>
                                <input class="form-control" type="text" name="address[{$address.id_order_carrier|escape:'html':'UTF-8'}][name1]"
                                       value="{$address.name1|escape:'htmlall':'UTF-8'}" maxlength="35"/>
                            </div>
                            <div class="form-group-flex">
                                <label class="control-label col-lg-4"><sup>*</sup> {l s='Name 2' mod='dhldp'}</label>
                                <input class="form-control" type="text" name="address[{$address.id_order_carrier|escape:'html':'UTF-8'}][name2]"
                                       value="{$address.name2|escape:'htmlall':'UTF-8'}" maxlength="35"/>
                            </div>
                            <div class="form-group-flex">
                                <label class="control-label col-lg-4">{l s='Communication person' mod='dhldp'}</label>
                                <input class="form-control" type="text" name="address[{$address.id_order_carrier|escape:'html':'UTF-8'}][comm_person]"
                                       value="{$address.comm_person|escape:'htmlall':'UTF-8'}" maxlength="50"/>
                            </div>
                        </div>
                        {if is_array($address.permission_confirmation)}
                        <div class="form-group-flex">
                            {if $address.permission_confirmation['permission_tpd']}
                                <div class="col-lg-12 alert alert-success">
                                    {l s='Permission for transferring e-mail address and phone number has been granted by customer' mod='dhldp'} ({$address.permission_confirmation['date_add']})
                                </div>
                            {else}
                                <div class="col-lg-12 alert alert-warning">
                                    {l s='Permission for transferring e-mail address and phone number has NOT been granted by customer' mod='dhldp'} ({$address.permission_confirmation['date_add']})
                                </div>
                            {/if}
                        </div>
                        {/if}
                        <div class="form-group-flex">
                            <label class="control-label col-lg-4"><sup>*</sup> {l s='Communication e-mail' mod='dhldp'}</label>
                            <input class="form-control" type="text" name="address[{$address.id_order_carrier|escape:'html':'UTF-8'}][comm_email]"
                                   value="{$address.comm_email|escape:'htmlall':'UTF-8'}" maxlength="70"{if is_array($address.permission_confirmation) && $address.permission_confirmation['permission_tpd'] == 0} disabled="disabled"{/if}/>
                        </div>
                        <div class="form-group-flex">
                            <label class="control-label col-lg-4"><sup>*</sup> {l s='Communication phone' mod='dhldp'}</label>
                            <input class="form-control" type="text" name="address[{$address.id_order_carrier|escape:'html':'UTF-8'}][comm_phone]"
                                   value="{$address.comm_phone|escape:'htmlall':'UTF-8'}" maxlength="20"{if is_array($address.permission_confirmation) && $address.permission_confirmation['permission_tpd'] == 0} disabled="disabled"{/if}/>
                        </div>
                        <div class="form-group-flex">
                            <label class="control-label col-lg-4">{l s='Communication mobile' mod='dhldp'}</label>
                            <input class="form-control" type="text" name="address[{$address.id_order_carrier|escape:'htmlall':'UTF-8'}][comm_mobile]"
                                   value="{$address.comm_mobile|escape:'htmlall':'UTF-8'}" maxlength="20"{if is_array($address.permission_confirmation) && $address.permission_confirmation['permission_tpd'] == 0} disabled="disabled"{/if}/>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group-flex">
                            <label class="control-label col-lg-4">{l s='Address type' mod='dhldp'}</label>
                            <input class="form-control" type="radio" name="address[{$address.id_order_carrier|escape:'htmlall':'UTF-8'}][address_type]"
                                   value="re" id="address_type_re_{$address.id_order_carrier|escape:'htmlall':'UTF-8'}"{if isset($address.address_type) && $address.address_type == 're'}checked="checked"{/if}/>
                            <label for="address_type_re_{$address.id_order_carrier|escape:'htmlall':'UTF-8'}">{l s='Regular' mod='dhldp'}</label>
                            <input class="form-control" type="radio" name="address[{$address.id_order_carrier|escape:'htmlall':'UTF-8'}][address_type]"
                                   value="ps" id="address_type_ps_{$address.id_order_carrier|escape:'htmlall':'UTF-8'}"{if isset($address.address_type) && $address.address_type == 'ps'}checked="checked"{/if}/>
                            <label for="address_type_ps_{$address.id_order_carrier|escape:'htmlall':'UTF-8'}">{l s='Packstation' mod='dhldp'}</label>
                            <input class="form-control" type="radio" name="address[{$address.id_order_carrier|escape:'htmlall':'UTF-8'}][address_type]"
                                   value="pf" id="address_type_pf_{$address.id_order_carrier|escape:'htmlall':'UTF-8'}"{if isset($address.address_type) && $address.address_type == 'pf'}checked="checked"{/if}/>
                            <label for="address_type_pf_{$address.id_order_carrier|escape:'htmlall':'UTF-8'}">{l s='Postfiliale' mod='dhldp'}</label>
                        </div>
                        <div class="address_type_ps">
                            <div class="form-group-flex">
                                <label class="control-label col-lg-4"><sup>*</sup> {l s='Packstation number' mod='dhldp'}</label>
                                <input class="form-control" type="text" name="address[{$address.id_order_carrier|escape:'htmlall':'UTF-8'}][ps_packstation_number]"
                                       value="{$address.ps_packstation_number|escape:'htmlall':'UTF-8'}" maxlength="5"/>
                            </div>
                            <div class="form-group-flex">
                                <label class="control-label col-lg-4"><sup>*</sup> {l s='Post number' mod='dhldp'}</label>
                                <input class="form-control" type="text" name="address[{$address.id_order_carrier|escape:'htmlall':'UTF-8'}][ps_post_number]"
                                       value="{$address.ps_post_number|escape:'htmlall':'UTF-8'}" maxlength="10"/>
                            </div>
                            <div class="form-group-flex">
                                <label class="control-label col-lg-4"><sup>*</sup> {l s='Zip' mod='dhldp'}</label>
                                <input class="form-control" type="text" name="address[{$address.id_order_carrier|escape:'htmlall':'UTF-8'}][ps_zip]"
                                       value="{$address.ps_zip|escape:'htmlall':'UTF-8'}" maxlength="5"/>
                            </div>
                            <div class="form-group-flex">
                                <label class="control-label col-lg-4"><sup>*</sup> {l s='City' mod='dhldp'}</label>
                                <input class="form-control" type="text" name="address[{$address.id_order_carrier|escape:'htmlall':'UTF-8'}][ps_city]"
                                       value="{$address.ps_city|escape:'htmlall':'UTF-8'}" maxlength="50"/>
                            </div>
                        </div>
                        <div class="address_type_pf">
                            <div class="form-group-flex">
                                <label class="control-label col-lg-4"><sup>*</sup> {l s='Postfiliale number' mod='dhldp'}</label>
                                <input class="form-control" type="text" name="address[{$address.id_order_carrier|escape:'htmlall':'UTF-8'}][pf_postfiliale_number]"
                                       value="{$address.pf_postfiliale_number|escape:'htmlall':'UTF-8'}" maxlength="5"/>
                            </div>
                            <div class="form-group-flex">
                                <label class="control-label col-lg-4"><sup>*</sup> {l s='Post number' mod='dhldp'}</label>
                                <input class="form-control" type="text" name="address[{$address.id_order_carrier|escape:'htmlall':'UTF-8'}][pf_post_number]"
                                       value="{$address.pf_post_number|escape:'htmlall':'UTF-8'}" maxlength="10"/>
                            </div>
                            <div class="form-group-flex">
                                <label class="control-label col-lg-4"><sup>*</sup> {l s='Zip' mod='dhldp'}</label>
                                <input class="form-control" type="text" name="address[{$address.id_order_carrier|escape:'htmlall':'UTF-8'}][pf_zip]"
                                       value="{$address.pf_zip|escape:'htmlall':'UTF-8'}" maxlength="5"/>
                            </div>
                            <div class="form-group-flex">
                                <label class="control-label col-lg-4"><sup>*</sup> {l s='City' mod='dhldp'}</label>
                                <input class="form-control" type="text" name="address[{$address.id_order_carrier|escape:'htmlall':'UTF-8'}][pf_city]"
                                       value="{$address.pf_city|escape:'htmlall':'UTF-8'}" maxlength="50"/>
                            </div>
                        </div>
                        <div class="address_type_re">
                            <div class="form-group-flex">
                                <label class="control-label col-lg-4"><sup>*</sup> {l s='Street name' mod='dhldp'}</label>
                                <input class="form-control" type="text" name="address[{$address.id_order_carrier|escape:'htmlall':'UTF-8'}][street_name]"
                                       value="{$address.street_name|escape:'htmlall':'UTF-8'}" maxlength="35"/>
                            </div>
                            <div class="form-group-flex">
                                <label class="control-label col-lg-4">{l s='Address addition' mod='dhldp'}</label>
                                <input class="form-control" type="text" name="address[{$address.id_order_carrier|escape:'htmlall':'UTF-8'}][address_addition]"
                                       value="{$address.address_addition|escape:'htmlall':'UTF-8'}" maxlength="35"/>
                            </div>
                            <div class="form-group-flex"{if !in_array($address.country_iso_code, ['BE', 'CZ', 'NL'])} style="display: none;"{/if}>
                                <label class="control-label col-lg-4">{l s='Dispatching information' mod='dhldp'}</label>
                                <input class="form-control" type="text" name="address[{$address.id_order_carrier|escape:'htmlall':'UTF-8'}][dispatching_information]"
                                       value="{$address.dispatching_information|escape:'htmlall':'UTF-8'}" maxlength="35"/>
                            </div>
                            <div class="form-group-flex">
                                <label class="control-label col-lg-4"><sup>*</sup> {l s='Zip' mod='dhldp'}</label>
                                <input class="form-control" type="text" name="address[{$address.id_order_carrier|escape:'htmlall':'UTF-8'}][zip]"
                                       value="{$address.zip|escape:'htmlall':'UTF-8'}" maxlength="10"/>
                            </div>
                            <div class="form-group-flex">
                                <label class="control-label col-lg-4"><sup>*</sup> {l s='Country ISO code' mod='dhldp'}</label>
                                <input class="form-control" type="text" name="address[{$address.id_order_carrier|escape:'htmlall':'UTF-8'}][country_iso_code]"
                                       value="{$address.country_iso_code|escape:'htmlall':'UTF-8'}" maxlength="3" disabled="disabled"/>
                            </div>
                            <div class="form-group-flex">
                                <label class="control-label col-lg-4">{l s='State' mod='dhldp'}</label>
                                <input class="form-control" type="text" name="address[{$address.id_order_carrier|escape:'htmlall':'UTF-8'}][state]"
                                       value="{$address.state|escape:'htmlall':'UTF-8'}" maxlength="9"/>
                            </div>
                            <div class="form-group-flex">
                                <label class="control-label col-lg-4"><sup>*</sup> {l s='City' mod='dhldp'}</label>
                                <input class="form-control" type="text" name="address[{$address.id_order_carrier|escape:'htmlall':'UTF-8'}][city]"
                                       value="{$address.city|escape:'htmlall':'UTF-8'}" maxlength="35"/>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" id="show_update_address" name="address[{$address.id_order_carrier|escape:'htmlall':'UTF-8'}][show_update_address]" value="{$address.show_update_address|escape:'htmlall':'UTF-8'}">
                </div>
            </div>
        </div>
    </div>
</div>