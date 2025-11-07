{**
* DHL Deutschepost
*
* @author    silbersaiten <info@silbersaiten.de>
* @copyright 2022 silbersaiten
* @license   See joined file licence.txt
* @category  Module
* @support   silbersaiten <support@silbersaiten.de>
* @version   1.0.17
* @link      http://www.silbersaiten.de
*}
<script type="text/javascript">
    var dhldp_ajax_path = '{$dhldp_ajax_path}';
    var dhl_products_params={};
    var cur_dhl_product_params={};
</script>
{capture assign=priceDisplayPrecisionFormat}{'%.'|cat:$smarty.const._PS_PRICE_DISPLAY_PRECISION_|cat:'f'|escape:'htmlall':'UTF-8'}{/capture}
{if ! $order_list}
    <p class="warning warn">{l s='There are no orders that use any of dhl carriers in your selection' mod='dhldp'}</p>
{else}
    {if isset($general_errors) && count($general_errors) > 0}
        <div class="alert alert-danger"><ul>
                {foreach from=$general_errors item=general_error}
                    <li>{$general_error|escape:'html':'UTF-8'}</li>
                {/foreach}
            </ul></div>
    {/if}
    {if isset($general_confirmations) && count($general_confirmations) > 0}
        <div class="alert alert-success"><ul>
                {foreach from=$general_confirmations item=general_confirmation}
                    <li>{$general_confirmation|escape:'html':'UTF-8'}</li>
                {/foreach}
            </ul></div>
    {/if}
    <div class="row dhl_panel">
        <div class="col-lg-12">
            <form method="post" class="form-inline" action="{$smarty.server.REQUEST_URI}">
                {foreach from=$order_list item=order_line}
                    {assign var=address value=$order_line.address nocache}
                    {assign var=addit_services value=$order_line.addit_services nocache}
                    {assign var=export_docs value=$order_line.export_docs nocache}

                    <script type="text/javascript">
                        dhl_products_params[{$order_line.id_order_carrier|escape:'html':'UTF-8'}]={$order_line.dhl_products|json_encode};
                        cur_dhl_product_params[{$order_line.id_order_carrier|escape:'html':'UTF-8'}]={};
                    </script>
                    <div id="ordercarrier-{$order_line.id_order_carrier|escape:'html':'UTF-8'}" class="panel ordercarrier-panel"{if isset($error_order_line) && in_array($order_line['id_order'], $error_order_line)} style="border-color: red;"{elseif isset($warning_order_line) && in_array($order_line['id_order'], $warning_order_line)} style="border-color: orange;"{elseif isset($success_order_line) && in_array($order_line['id_order'], $success_order_line)} style="border-color: #00ff00;"{else}{/if}>
                        <div class="panel-heading">{l s='Order #' mod='dhldp'} <span class="badge">{$order_line.id_order|escape:'html':'UTF-8'}</span>
                            {l s='Order Ref.' mod='dhldp'} <span class="badge">{$order_line.reference|escape:'html':'UTF-8'}</span>
                            {l s='Customer' mod='dhldp'} <span class="badge">{$order_line.customer|escape:'html':'UTF-8'}</span>
                            {l s='Country' mod='dhldp'} <span class="badge">{$order_line.country|escape:'html':'UTF-8'}</span>
                            <a class="button" href="?tab=AdminOrders&amp;id_order={$order_line['id_order']|intval}&amp;vieworder&amp;token={getAdminToken tab='AdminOrders'}">
                                {l s='View order' mod='dhldp'}
                            </a>
                        </div>
                        {if isset($dhl_warnings) && $dhl_warnings|@count}

                            <div class="alert alert-warning"><ul>

                                    {if isset($dhl_confirmations) && $dhl_confirmations|@count}
                                        {if isset($dhl_warnings_title)}
                                            <p class="mb-1">
                                                <strong>{$dhl_warnings_title}</strong>
                                            </p>
                                        {/if}
                                    {else}
                                        {if isset($dhl_with_warning)}
                                            {if $dhl_with_warning == false}
                                                <div class="form-group">
                                                    <label class="control-label">
                                                        <strong>{l s='Create a label anyway' mod='dhldp'}</strong>
                                                    </label>
                                                    <input type="checkbox" id="submitDHLDPDhlCreateLabelNoValidation" value="">
                                                </div>
                                            {/if}
                                        {/if}
                                    {/if}
                                    {foreach from=$dhl_warnings item=dhl_warning}

                                        <li>{$dhl_warning|escape:'htmlall':'UTF-8'}</li>

                                    {/foreach}
                                </ul>
                            </div>

                        {/if}
                        {if isset($orders_errors[$order_line['id_order']]) && count($orders_errors[$order_line['id_order']]) > 0}
                            <div class="alert alert-danger"><ul>
                                    {foreach from=$orders_errors[$order_line['id_order']] item=order_error}
                                        <li>{$order_error|escape:'html':'UTF-8'}</li>
                                    {/foreach}
                                </ul></div>
                        {/if}
                        {if isset($orders_confirmations[$order_line['id_order']]) && count($orders_confirmations[$order_line['id_order']]) > 0}
                            <div class="alert alert-success"><ul>
                                    {foreach from=$orders_confirmations[$order_line['id_order']] item=order_confirmation}
                                        <li>{$order_confirmation|escape:'html':'UTF-8'}</li>
                                    {/foreach}
                                </ul></div>
                        {/if}
                        {if isset($orders_warnings[$order_line['id_order']]) && count($orders_warnings[$order_line['id_order']]) > 0}
                            <div class="alert alert-warning"><ul>

                                    {if isset($orders_confirmations[$order_line['id_order']]) && $orders_confirmations[$order_line['id_order']]|@count}
                                        {if isset($warnings_title)}
                                            <p class="mb-1">
                                                <strong>{$warnings_title}</strong>
                                            </p>
                                        {/if}
                                    {else}
                                        {if isset($dhl_with_warning)}
                                            {if $dhl_with_warning == false}
                                                <div class="form-group">
                                                    <label class="control-label">
                                                        <strong>{l s='Create a label anyway' mod='dhldp'}</strong>
                                                    </label>
                                                            <input type="checkbox"
                                                                   name="carrier[{$order_line.id_order_carrier|escape:'html':'UTF-8'}][create_label_no_validation]"
                                                                   value="1">


                                                </div>
                                            {/if}
                                        {/if}
                                    {/if}
                                    {foreach from=$orders_warnings[$order_line['id_order']] item=order_warning}
                                        <li>{$order_warning|escape:'html':'UTF-8'}</li>
                                    {/foreach}
                                </ul></div>
                        {/if}
                        {if isset($order_line.dhl_assigned) && $order_line.dhl_assigned == true}
                            <table class="table std" cellspacing="0" cellpadding="0" style="width:100%;">
                                <thead>
                                <tr>
                                    <th>{l s='ID' mod='dhldp'}</th>
                                    <th>{l s='Date' mod='dhldp'}</th>
                                    <th>{l s='Product / Shipment date' mod='dhldp'}</th>
                                    <th>{l s='Parameters' mod='dhldp'}</th>
                                    <th>{l s='Options' mod='dhldp'}</th>
                                    <th>{l s='Action' mod='dhldp'}</th>
                                </tr>
                                </thead>
                                {if isset($order_line.labels)}
                                <tbody>
                                {if count($order_line.labels) > 1}<tr><td colspan="6">
                                        <button data-icon-more="{if $is177}expand_more{else}icon-angle-double-down{/if}" data-icon-less="{if $is177}expand_less{else}icon-angle-double-up{/if}" type="button" class="button btn btn-default btn-sm" id="toggleAllDHLLabelsForOrder">
                                            {if $is177}<i class="material-icons">expand_more</i>{else}<i class="icon-angle-double-down"></i>{/if}
                                            {l s='Show/hide all labels for this order' mod='dhldp'}
                                        </button>
                                    </td></tr>
                                {/if}
                                {foreach from=$order_line.labels item=carrier name=dhl_label}
                                    <tr{if !$smarty.foreach.dhl_label.last} class="hiddenLabel" style="display: none;"{/if}>
                                        <td>{$carrier.id_dhldp_label|escape:'htmlall':'UTF-8'}</td>
                                        <td>{Tools::displayDate($carrier.date_add, true)|escape:'htmlall':'UTF-8'}</td>
                                        <td>
                                            {*{$order_line.carrier_name|escape:'html':'UTF-8'}<br>*}
                                            {$carrier.product_name|escape:'htmlall':'UTF-8'}<br>
                                            {if isset($carrier.is_return) && $carrier.is_return == 1}
                                                <span class="badge badge-warning">{l s='Return label' mod='dhldp'}</span><br>
                                            {/if}
                                            {if isset($carrier.with_return) && $carrier.with_return == 1}
                                                <span class="badge badge-warning">{l s='With return label' mod='dhldp'}</span><br>
                                            {/if}
                                            {if $carrier.shipment_date != '0000-00-00 00:00:00'}
                                                {Tools::displayDate($carrier.shipment_date)|escape:'htmlall':'UTF-8'}
                                            {/if}
                                        </td>
                                        <td>
                                            {l s='Weight' mod='dhldp'}: {Tools::ps_round($carrier.packages[0]['weight'], 1)|escape:'htmlall':'UTF-8'} {l s='kg' mod='dhldp'};
                                            {l s='Length' mod='dhldp'}: {$carrier.packages[0]['length']|escape:'htmlall':'UTF-8'} {l s='cm' mod='dhldp'} x
                                            {l s='Width' mod='dhldp'}: {$carrier.packages[0]['width']|escape:'htmlall':'UTF-8'} {l s='cm' mod='dhldp'} x
                                            {l s='Height' mod='dhldp'}: {$carrier.packages[0]['height']|escape:'htmlall':'UTF-8'} {l s='cm' mod='dhldp'};
                                        </td>
                                        <td>
                                            <div id="dhl_options">
                                                {if isset($carrier.options_decoded['DeclaredValueOfGoods'])}
                                                    <div>
                                                        {l s='Declared value' mod='dhldp'}
                                                        <div class="input-group fixed-width-x2 pull-right">
                                                            {$carrier.options_decoded['DeclaredValueOfGoods']|escape:'htmlall':'UTF-8'} {$carrier.options_decoded['DeclaredValueOfGoodsCurrency']|escape:'htmlall':'UTF-8'}
                                                        </div>
                                                    </div>
                                                {/if}
                                                {if isset($carrier.options_decoded['COD']['CODAmount'])}
                                                    <div>
                                                        {l s='COD amount' mod='dhldp'}
                                                        <div class="input-group fixed-width-x2 pull-right">
                                                            {$carrier.options_decoded['COD']['CODAmount']|escape:'htmlall':'UTF-8'} {$carrier.options_decoded['COD']['CODCurrency']|escape:'htmlall':'UTF-8'}
                                                        </div>
                                                    </div>
                                                {/if}
                                                {if isset($carrier.options_decoded['HigherInsurance']['InsuranceAmount'])}
                                                    <div>
                                                        {l s='Insurance amount' mod='dhldp'}
                                                        <div class="input-group fixed-width-x2 pull-right">
                                                            {$carrier.options_decoded['HigherInsurance']['InsuranceAmount']|escape:'htmlall':'UTF-8'} {$carrier.options_decoded['HigherInsurance']['InsuranceCurrency']|escape:'htmlall':'UTF-8'}
                                                        </div>
                                                    </div>
                                                {/if}
                                                {if isset($carrier.options_decoded['CheckMinimumAge']['MinimumAge']) && $carrier.options_decoded['CheckMinimumAge']['MinimumAge'] > 0}
                                                    <div>
                                                        {l s='Minimum age' mod='dhldp'}
                                                        <div class="input-group fixed-width-x2 pull-right">
                                                            {$carrier.options_decoded['CheckMinimumAge']['MinimumAge']|escape:'htmlall':'UTF-8'} {l s='years' mod='dhldp'}
                                                        </div>
                                                    </div>
                                                {/if}
                                            </div>
                                        </td>
                                        <td>
                                            {l s='Shipment number' mod='dhldp'}: {$carrier.shipment_number|escape:'htmlall':'UTF-8'}<br>
                                            {if $carrier.label_url}
                                                {if $smarty.foreach.dhl_label.last}
                                                    <input type="hidden" name="printLabel[{$carrier.id_order_carrier|escape:'html':'UTF-8'}][label_url]" value="{$carrier.label_url|escape:'html':'UTF-8'}" />
                                                {/if}

                                                <a href="{$carrier.label_url|escape:'html':'UTF-8'}" class="btn btn-primary btn-sm" target="_blank"><i class="icon-print"></i> {l s='Print label' mod='dhldp'}</a>
                                            {/if}
                                            {if $carrier.return_label_url != ''}
                                                <a class="btn btn-primary btn-sm" href="{$carrier.return_label_url|escape:'html':'UTF-8'}" target="_blank"><i class="icon-print"></i> {l s='Print return label' mod='dhldp'}</a>
                                            {/if}
                                            {if $carrier.export_label_url != ''}
                                                <a class="btn btn-primary btn-sm" href="{$carrier.export_label_url|escape:'html':'UTF-8'}" target="_blank"><i class="icon-print"></i> {l s='Print export doc' mod='dhldp'}</a>
                                            {/if}
                                            {if $carrier.cod_label_url != ''}
                                                <a class="btn btn-primary btn-sm" href="{$carrier.cod_label_url|escape:'html':'UTF-8'}" target="_blank"><i class="icon-print"></i> {l s='Print COD doc' mod='dhldp'}</a>
                                            {/if}
                                            {if $carrier.tracking_url}
                                                <a class="btn btn-default btn-sm" href="{$carrier.tracking_url|escape:'html':'UTF-8'}" target="_blank"><i class="icon-search"></i> {l s='Tracking' mod='dhldp'}</a>
                                            {/if}
                                            <br>
                                            <a class="btn btn-default dhldp-get-tracking-data btn-sm" href="#" data-tracking-number="{$carrier.shipment_number|escape:'html':'UTF-8'}"><i class="icon-truck"></i> {l s='Update tracking data' mod='dhldp'}</a>
                                            <div class="dhldp-last-tracking-data"></div>
                                        </td>
                                    </tr>
                                {/foreach}
                                {/if}
                                <tr>
                                    <td colspan="3">
                                        <input type="hidden" name="carrier[{$order_line.id_order_carrier|escape:'html':'UTF-8'}][order_id]" value="{$order_line.id_order|escape:'html':'UTF-8'}" />
                                        <input type="hidden" name="carrier[{$order_line.id_order_carrier|escape:'html':'UTF-8'}][order_reference]" value="{$order_line.reference|escape:'html':'UTF-8'}" />
                                        <input type="hidden" name="carrier[{$order_line.id_order_carrier|escape:'html':'UTF-8'}][id_order_carrier]" value="{$order_line.id_order_carrier|escape:'html':'UTF-8'}" />
                                        <input type="hidden" name="carrier[{$order_line.id_order_carrier|escape:'html':'UTF-8'}][id_carrier]" value="{$order_line.id_carrier|escape:'html':'UTF-8'}" />
                                        <input type="hidden" name="carrier[{$order_line.id_order_carrier|escape:'html':'UTF-8'}][id_address]" value="{$order_line.id_address_delivery|escape:'html':'UTF-8'}" />
                                        <div class="row">
                                            {$order_line.carrier_name|escape:'html':'UTF-8'}
                                        </div>
                                        <div class="row">
                                            <select id="dhl_product_code" name="carrier[{$order_line.id_order_carrier|escape:'html':'UTF-8'}][dhl_product_code]" class="fixed-width-lg" style="display: inline-block;">
                                                {foreach from=$order_line.dhl_products key=product_index item=product_data}
                                                    <option value="{$product_data.fullcode|escape:'html':'UTF-8'}"{if (isset($smarty.post.carrier[{$order_line.id_order_carrier|escape:'html':'UTF-8'}]['dhl_product_code'])) && ($smarty.post.carrier[{$order_line.id_order_carrier|escape:'html':'UTF-8'}]['dhl_product_code'] == $product_data.fullcode)} selected="selected"{elseif (isset($order_line.default_dhl_product_code) && $order_line.default_dhl_product_code == $product_data.fullcode)} selected="selected"{/if}>{$product_data.fullname|escape:'html':'UTF-8'}</option>
                                                {/foreach}
                                            </select>
                                        </div>
                                        <div class="row">
                                            {if $is177}<div class="input-group datepicker">{/if}
                                                <input class="form-control{if $is177} custom-select{else} datepicker{/if}" type="text" name="carrier[{$order_line.id_order_carrier|escape:'html':'UTF-8'}][dhl_shipment_date]"
                                                       value="{if isset($smarty.post.carrier[{$order_line.id_order_carrier|escape:'html':'UTF-8'}]['dhl_shipment_date'])}{$smarty.post.carrier[{$order_line.id_order_carrier|escape:'html':'UTF-8'}]['dhl_shipment_date']|escape:'htmlall':'UTF-8'}{else}{$shipment_date|escape:'htmlall':'UTF-8'}{/if}" maxlength="10"/>
                                                {if $is177}</div>{/if}
                                            <span class="help-block">{l s='yyyy-mm-dd' mod='dhldp'}</span>
                                        </div>
                                    </td>
                                    <td align="left" colspan="3">
                                        <div id="dhl_params" class="row">
                                            <div class="col-lg-3">
                                                {l s='Weight' mod='dhldp'}
                                                <div class="input-group">
                                                    <input type="text" class="parcel_weight" name="carrier[{$order_line.id_order_carrier|escape:'html':'UTF-8'}][weight]" size="3"
                                                           value="{$order_line.input_default_values.weight|escape:'html':'UTF-8'}" />
                                                    <span class="input-group-addon">{l s='kg' mod='dhldp'}</span>
                                                </div>
                                            </div>
                                            <div class="col-lg-3">
                                                {l s='Width' mod='dhldp'}
                                                <div class="input-group">
                                                    <input type="text" size="3" name="carrier[{$order_line.id_order_carrier|escape:'html':'UTF-8'}][width]"
                                                           value="{$order_line.input_default_values.width|escape:'html':'UTF-8'}" />
                                                    <span class="input-group-addon">{l s='cm' mod='dhldp'}</span>
                                                </div>
                                            </div>
                                            <div class="col-lg-3">
                                                {l s='Height' mod='dhldp'}
                                                <div class="input-group">
                                                    <input type="text" size="3" name="carrier[{$order_line.id_order_carrier|escape:'html':'UTF-8'}][height]"
                                                           value="{$order_line.input_default_values.height|escape:'html':'UTF-8'}" />
                                                    <span class="input-group-addon">{l s='cm' mod='dhldp'}</span>
                                                </div>
                                            </div>
                                            <div class="col-lg-3">
                                                {l s='Length' mod='dhldp'}
                                                <div class="input-group">
                                                    <input type="text" size="3" name="carrier[{$order_line.id_order_carrier|escape:'html':'UTF-8'}][length]"
                                                           value="{$order_line.input_default_values.length|escape:'html':'UTF-8'}" />
                                                    <span class="input-group-addon">{l s='cm' mod='dhldp'}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="dhldp_dhl_addit_services_inline">
                                            <div class="form-group-flex">
                                                <label class="control-label col-lg-6">{l s='Parcel outlet routing' mod='dhldp'}
                                                    <span class="help-box{if $is16}-f{/if}" data-toggle="popover" data-content="{l s='Your undeliverable item (recipient unknown
or acceptance refused) gets a second chance to reach the recipient. Rather than being
returned immediately to you, the undeliverable item will be held at the nearest retail
outlet that has a parcel collection point for collection by the recipient. Your recipient
will be informed of this by e-mail. If the item is collected, the time and costs involved in
returning it can be avoided' mod='dhldp'}"></span>
                                                </label>
                                                <div class="col-lg-6">
                                                    <input class="form-control" type="checkbox" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][ParcelOutletRouting]"
                                                            {if $addit_services.ParcelOutletRouting == 1}{if is_array($order_line.permission_confirmation) && !$order_line.permission_confirmation['permission_tpd']}{else} checked="checked"{/if}{/if}
                                                           value="1"{if is_array($order_line.permission_confirmation) && !$order_line.permission_confirmation['permission_tpd']} disabled="disabled"{/if}/>
                                                </div>
                                            </div>
                                            <div class="form-group-flex">
                                                <label class="control-label col-lg-6">{l s='Parcel outlet routing: details' mod='dhldp'}
                                                    <span class="help-box{if $is16}-f{/if}" data-toggle="popover" data-content="{l s='Details can be an email-address, if not set receiver email will be used' mod='dhldp'}"></span>
                                                </label>
                                                <div class="col-lg-6">
                                                    <input type="email" class="form-control" style="width: 100%;" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][ParcelOutletRouting_details]" value="{$addit_services.ParcelOutletRouting_details|escape:'htmlall':'UTF-8'}" />
                                                    {*                            <textarea class="form-control" style="width: 100%;word-wrap: break-word; resize: none; height: 85px;" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][ParcelOutletRouting_details]"*}
                                                    {*                                      maxlength="100">{$addit_services.ParcelOutletRouting_details|escape:'htmlall':'UTF-8'}</textarea>*}
                                                    <span class="help-block"></span>
                                                </div>
                                            </div>
                                            {if is_array($order_line.permission_confirmation)}
                                                <div class="form-group-flex">
                                                    {if $order_line.permission_confirmation['permission_tpd']}
                                                        <div class="col-lg-6"></div>
                                                        <div class="col-lg-6 alert alert-success">
                                                            {l s='Permission for transferring e-mail address and phone number has been granted by customer' mod='dhldp'} ({$order_line.permission_confirmation['date_add']})
                                                        </div>
                                                    {else}
                                                        <div class="col-lg-6"></div>
                                                        <div class="col-lg-6 alert alert-warning">
                                                            {l s='Permission for transferring e-mail address and phone number has NOT been granted by customer' mod='dhldp'} ({$order_line.permission_confirmation['date_add']})
                                                        </div>
                                                    {/if}
                                                </div>
                                            {/if}
                                        </div>
                                        <div class="row param-footer">
                                            <div class="col-lg-12">
                                                <button type="button" data-icon-more="{if $is177}expand_more{else}icon-angle-double-down{/if}" data-icon-less="{if $is177}expand_less{else}icon-angle-double-up{/if}" class="button btn btn-default btn-sm" id="submitDhlAdditServices" name="submitDhlAdditServices">{if $is177}<i class="material-icons">expand_more</i>{else}<i class="icon-angle-double-down"></i>{/if} {l s='Additional services' mod='dhldp'} </button>
                                                <button type="button" data-icon-more="{if $is177}expand_more{else}icon-angle-double-down{/if}" data-icon-less="{if $is177}expand_less{else}icon-angle-double-up{/if}" class="button btn btn-default btn-sm" id="submitDhlUpdateAddress" name="submitDhlUpdateAddress">{if $is177}<i class="material-icons">expand_more</i>{else}<i class="icon-angle-double-down"></i>{/if} {l s='Update delivery address' mod='dhldp'} </button>
                                                <button type="button" data-icon-more="{if $is177}expand_more{else}icon-angle-double-down{/if}" data-icon-less="{if $is177}expand_less{else}icon-angle-double-up{/if}" class="button btn btn-default btn-sm" id="submitDhlExportDocuments" name="submitDhlExportDocuments">{if $is177}<i class="material-icons">expand_more</i>{else}<i class="icon-angle-double-down"></i>{/if} {l s='Export documents' mod='dhldp'} </button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="6">
                                        {include file="$self/views/templates/hook/additional-services.tpl"}
                                        {include file="$self/views/templates/hook/export-documents.tpl"}
                                        {include file="$self/views/templates/hook/update-address.tpl"}
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        {else}
                            <span class="alert-warning">{l s='no dhl carrier' mod='dhldp'}</span>
                        {/if}
                    </div>
                {/foreach}
                <div class="panel card footer-panel">
                    <p class="alert alert-info">{l s='The labels will be generated only for those orders that don\'t have them generated already, the existing labels will not change' mod='dhldp'}</p>
                    <p>
                        <button type="submit" class="btn btn-success" name="generateMultipleLabels" id="generateMultipleLabels">{l s='Generate labels' mod='dhldp'}</button>
                        <button type="submit" class="btn btn-primary" name="printMultipleLabels" id="printMultipleLabels"><i class="icon-print"></i> {l s='Print last labels' mod='dhldp'}</button>
                    </p>
                </div>
            </form>
        </div>
    </div>
{/if}