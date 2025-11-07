{**
* DHL Deutschepost
*
* @author    silbersaiten <info@silbersaiten.de>
* @copyright 2022 silbersaiten
* @license   See joined file licence.txt
* @category  Module
* @support   silbersaiten <support@silbersaiten.de>
* @version   1.1.0
* @link      http://www.silbersaiten.de
*}
<div class="row" id="dhldp_dhl_export_documents" style="{if !isset($export_docs.show_dhl_export_documents) || (isset($export_docs.show_dhl_export_documents) && $export_docs.show_dhl_export_documents != 1)}display: none;{/if}">
    <div class="panel card">
        <div class="panel-heading card-header"><button type="button" id="collapseDHLDPDhlExportDocuments" class="button btn btn-default btn-sm collapseDHLDPDhlExportDocuments">{if $is177}<i class="material-icons">expand_less</i>{else}<i class="icon-angle-double-up"></i>{/if} </button> {l s='Export documents' mod='dhldp'} </div>
        <div class="row form-wrapper form-horizontal">
            <div class="col-lg-6">
                <div class="form-group-flex">
                    <label class="control-label col-lg-4">{l s='Invoice number' mod='dhldp'}</label>
                    <div class="col-lg-8">
                        <input class="form-control" type="text" name="export_docs[{$export_docs.id_order_carrier|escape:'html':'UTF-8'}][invoiceNumber]"
                           value="{$export_docs.invoiceNumber|escape:'htmlall':'UTF-8'}" maxlength="35"/>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-4 required">{l s='Export type' mod='dhldp'}</label>
                    <div class="col-lg-8">
                        <select name="export_docs[{$export_docs.id_order_carrier|escape:'html':'UTF-8'}][exportType]" class="form-control" >
                            {foreach from=$export_docs.exporttype_options key=option_key item=option_value}
                                <option value="{$option_key|escape:'html':'UTF-8'}"{if $export_docs.exportType == $option_key} selected="selected"{/if}>{$option_value|escape:'html':'UTF-8'}</option>
                            {/foreach}
                        </select>
                        <span class="help-block">
                            <br>{l s='If you selected OTHER, then please fill "Description of export type"' mod='dhldp'}
                        </span>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-4 required">{l s='Description of export type' mod='dhldp'}</label>
                    <div class="col-lg-8">
                        <textarea class="textarea-autosize" style="width: 100%;word-wrap: break-word; resize: none; height: 85px;" name="export_docs[{$export_docs.id_order_carrier|escape:'html':'UTF-8'}][exportTypeDescription]" maxlength="256">{$export_docs.exportTypeDescription|escape:'htmlall':'UTF-8'}</textarea>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-4">{l s='Terms of trade' mod='dhldp'}</label>
                    <div class="col-lg-8">
                        <select name="export_docs[{$export_docs.id_order_carrier|escape:'html':'UTF-8'}][termsOfTrade]" class="form-control" >
                            <option value="">-</option>
                            {foreach from=$export_docs.termsoftrade_options key=option_key item=option_value}
                                <option value="{$option_key|escape:'html':'UTF-8'}"{if $export_docs.termsOfTrade == $option_key} selected="selected"{/if}>{$option_value|escape:'html':'UTF-8'}</option>
                            {/foreach}
                        </select>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="form-group-flex">
                    <label class="control-label col-lg-4 required">{l s='Place of commital' mod='dhldp'}</label>
                    <div class="col-lg-8">
                        <input class="form-control" type="text" name="export_docs[{$export_docs.id_order_carrier|escape:'html':'UTF-8'}][placeOfCommital]"
                               value="{$export_docs.placeOfCommital|escape:'htmlall':'UTF-8'}" maxlength="35"/>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-4 required">{l s='Additional custom fees' mod='dhldp'}</label>
                    <div class="col-lg-8">
                        <div class="input-group{if $is177} money-type {else} fixed-width-x2 pull-right{/if} fixed-width-x2">
                            {if $is177}
                                <div class="input-group-prepend">
                                    <span class="input-group-text">{l s='euro' mod='dhldp'}</span>
                                </div>
                            {/if}
                            <input class="form-control" type="text" name="export_docs[{$export_docs.id_order_carrier|escape:'html':'UTF-8'}][additionalFee]"
                                   value="{$export_docs.additionalFee|escape:'htmlall':'UTF-8'}" maxlength="10"/>
                            {if !$is177}
                            <div class="input-group-addon">{l s='euro' mod='dhldp'} </div>
                            {/if}
                        </div>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-4">{l s='Permit number' mod='dhldp'}</label>
                    <div class="col-lg-8">
                        <input class="form-control" type="text" name="export_docs[{$export_docs.id_order_carrier|escape:'html':'UTF-8'}][permitNumber]"
                               value="{$export_docs.permitNumber|escape:'htmlall':'UTF-8'}" maxlength="10"/>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-4">{l s='Attestation number' mod='dhldp'}</label>
                    <div class="col-lg-8">
                        <input class="form-control" type="text" name="export_docs[{$export_docs.id_order_carrier|escape:'html':'UTF-8'}][attestationNumber]"
                               value="{$export_docs.attestationNumber|escape:'htmlall':'UTF-8'}" maxlength="35"/>
                    </div>
                </div>
                <div class="form-group-flex">
                    <label class="control-label col-lg-4">{l s='Electronic export notification' mod='dhldp'}</label>
                    <div class="col-lg-8">
                        <input class="form-control" type="checkbox" value="1" name="export_docs[{$export_docs.id_order_carrier|escape:'html':'UTF-8'}][WithElectronicExportNtfctn]"{if $export_docs.WithElectronicExportNtfctn == 1} checked="checked"{/if}/>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-12">
                {if $export_docs.exportdoc_positions_limit_exceed == true}<div class="alert alert-warning">{l s='Maximum quantity of positions is exceed. You see first 99 positions of order' mod='dhldp'}</div>{/if}
                <div class="alert alert-info">
                    <div> - {l s='Sum of weights of positions must be equal to weight of package' mod='dhldp'}</div>
                    <div class="dhlp_new_release"> - {l s='If you don\'t want to add product in export document, then set "Amount" to 0' mod='dhldp'}</div>
                </div>
                <table class="table">
                    <thead>
                        <th>
                            {l s='Description' mod='dhldp'}
                        </th>
                        <th>
                            {l s='Country code origin' mod='dhldp'}
                        </th>
                        <th>
                            {l s='Customs tariff number' mod='dhldp'}
                        </th>
                        <th>
                            {l s='Amount' mod='dhldp'}
                        </th>
                        <th>
                            {l s='Net weight' mod='dhldp'}
                        </th>
                        <th>
                            {l s='Customs value' mod='dhldp'}
                        </th>
                    </thead>
                    <tbody>
                        {if isset($export_docs.exportdoc_positions) && count($export_docs.exportdoc_positions) > 0}
                            {foreach from=$export_docs.exportdoc_positions key=product_key item=product_value name=position}
                            <tr class="export_doc_position {if $smarty.foreach.position.index%2} odd{/if}">
                                <td>
                                    <textarea class="textarea-autosize form-control" style="width: 100%;word-wrap: break-word; resize: none; height: 85px;" name="export_docs[{$export_docs.id_order_carrier|escape:'html':'UTF-8'}][ExportDocPosition][{$smarty.foreach.position.index}][description]" maxlength="256">{$product_value.description|escape:'htmlall':'UTF-8'}</textarea>
                                </td>
                                <td>
                                    <input type="text" class="form-control" name="export_docs[{$export_docs.id_order_carrier|escape:'html':'UTF-8'}][ExportDocPosition][{$smarty.foreach.position.index}][countryCodeOrigin]" value="{$product_value.countryCodeOrigin|escape:'htmlall':'UTF-8'}" maxlength="2">
                                </td>
                                <td>
                                    <input type="text" class="form-control" name="export_docs[{$export_docs.id_order_carrier|escape:'html':'UTF-8'}][ExportDocPosition][{$smarty.foreach.position.index}][customsTariffNumber]" value="{$product_value.customsTariffNumber|escape:'htmlall':'UTF-8'}" maxlength="10">
                                </td>
                                <td>
                                    <input type="text" class="form-control position_amount" name="export_docs[{$export_docs.id_order_carrier|escape:'html':'UTF-8'}][ExportDocPosition][{$smarty.foreach.position.index}][amount]" value="{$product_value.amount|escape:'htmlall':'UTF-8'}" maxlength="10">
                                </td>
                                <td>
                                    <input type="text" class="form-control position_unit_weight" name="export_docs[{$export_docs.id_order_carrier|escape:'html':'UTF-8'}][ExportDocPosition][{$smarty.foreach.position.index}][netWeightInKG]" value="{$product_value.netWeightInKG|escape:'htmlall':'UTF-8'}" maxlength="10">
                                </td>
                                <td>
                                    <input type="text" class="form-control" name="export_docs[{$export_docs.id_order_carrier|escape:'html':'UTF-8'}][ExportDocPosition][{$smarty.foreach.position.index}][customsValue]" value="{$product_value.customsValue|escape:'htmlall':'UTF-8'}" maxlength="10">
                                </td>
                            </tr>
                            {/foreach}
                        {/if}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <input type="hidden" id="show_dhl_export_documents" name="export_docs[{$export_docs.id_order_carrier|escape:'htmlall':'UTF-8'}][show_dhl_export_documents]" value="{$export_docs.show_dhl_export_documents|escape:'htmlall':'UTF-8'}">
</div>
<script type="text/javascript">
    $(function() {
        $("textarea").maxlength(
            {
                text: '%length / %maxlength'
            }
        );

        if ($("select[name*='[{$export_docs.id_order_carrier|escape:'html':'UTF-8'}][exportType]']").val() == 'OTHER') {
            $("textarea[name*='[{$export_docs.id_order_carrier|escape:'html':'UTF-8'}][exportTypeDescription]']").closest('div.form-group-flex').show()
        } else {
            $("textarea[name*='[{$export_docs.id_order_carrier|escape:'html':'UTF-8'}][exportTypeDescription]']").closest('div.form-group-flex').hide()
        }

        $(document).on('change', "select[name*='[{$export_docs.id_order_carrier|escape:'html':'UTF-8'}][exportType]']", function() {
            if ($("select[name*='[{$export_docs.id_order_carrier|escape:'html':'UTF-8'}][exportType]']").val() == 'OTHER') {
                $("textarea[name*='[{$export_docs.id_order_carrier|escape:'html':'UTF-8'}][exportTypeDescription]']").closest('div.form-group-flex').show()
            } else {
                $("textarea[name*='[{$export_docs.id_order_carrier|escape:'html':'UTF-8'}][exportTypeDescription]']").closest('div.form-group-flex').hide()
            }
        })
    });
</script>