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
<div id="dhldp" class="panel">
    {if ! $allow_to_use}
        <div class="warn">{l s='You must save this product before using it' mod='dhldp'}</div>
    {else}
        <div class="form-group">
            <label class="control-label col-lg-3">
                {l s='Customs tarif number' mod='dhldp'}
            </label>
            <div class="col-lg-5">
                <input type="text" name="dhldp_ctn" class="form-control" value="{$ctn|escape:'html':'UTF-8'}" maxlength="10"/>
            </div>
        </div>
        <div class="form-group">
            <label class="control-label col-lg-3">
                {l s='Country of origin' mod='dhldp'}
            </label>
            <div class="col-lg-5">
                <input type="text" name="dhldp_coo" class="form-control" value="{$coo|escape:'html':'UTF-8'}" maxlength="2"/>
                <span class="help-block">
                    {l s='ISO code of country' mod='dhldp'}
                </span>
            </div>
        </div>
        {if $combinations}
            <div class="form-group">
                <table class="table table-striped table-no-bordered">
                    <thead>
                    <tr>
                        <th>{l s='Id attribute' mod='dhldp'}</th>
                        <th>{l s='Combination name' mod='dhldp'}</th>
                        <th>{l s='Combination reference' mod='dhldp'}</th>
                        <th>{l s='Customs tariff number' mod='dhldp'}</th>
                    </tr>
                    </thead>
                    <tbody>
                    {foreach from=$combinations item=combination}
                        <tr>
                            <td>
                                {$combination.id_product_attribute|escape:'html':'UTF-8'}
                            </td>
                            <td>
                                <img src="{$link->getImageLink(strtolower($product_link_rewrite), $combination.id_image, 'small_default')|escape:'html':'UTF-8'}">
                                {$product_name|escape:'html':'UTF-8'} - {$combination.attribute_name|escape:'html':'UTF-8'}
                            </td>
                            <td>
                                {$combination.reference|escape:'html':'UTF-8'}
                            </td>
                            <td>
                                <input class="form-control"
                                       type="text"
                                       name="c_dhldp_ctn[{$combination.id_product_attribute|escape:'html':'UTF-8'}]"
                                       value="{$combination.custom_tariff_number|escape:'html':'UTF-8'}"/>
                            </td>
                        </tr>
                    {/foreach}
                    </tbody>
                </table>
            </div>
        {/if}
        {if $show_buttons}
        <div class="panel-footer">
            <a href="{$link->getAdminLink('AdminProducts')|escape:'quotes':'UTF-8'}" class="btn btn-default"><i class="process-icon-cancel"></i> {l s='Cancel' mod='dhldp'}</a>
            <button type="submit" name="submitAddproduct" class="btn btn-default pull-right"><i class="process-icon-save"></i> {l s='Save' mod='dhldp'}</button>
            <button type="submit" name="submitAddproductAndStay" class="btn btn-default pull-right"><i class="process-icon-save"></i> {l s='Save and stay' mod='dhldp'}</button>
        </div>
        {/if}
    {/if}
</div>