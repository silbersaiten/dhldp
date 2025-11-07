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
{extends file='customer/page.tpl'}
{block name='page_title'}
    {l s='Return DHL Label' mod='dhldp'}
{/block}

{block name='page_content'}
    {if isset($form_errors_quantity) && $form_errors_quantity}
        <aside id="notifications">
            <div class="container">
                <article class="alert alert-danger" role="alert" data-alert="danger">
                    <ul>
                        <li> {l s='You cannot create DHL return label. Please contact us.' mod='dhldp'}</li>
                    </ul>
                </article>
                {foreach $form_errors as $error}
                    <article class="alert alert-danger" role="alert" data-alert="danger">
                        <ul>
                            <li> {$error|escape:'html':'UTF-8'}</li>
                        </ul>
                    </article>
                {/foreach}
            </div>
        </aside>
    {/if}
    {if isset($show_form) && ($show_form == true)}
        <div class="dhl-return-label-form">
            <h6>{l s='Here is a form for creating return label. Please fill sender address' mod='dhldp'}</h6>
            <form action="{$link->getModuleLink('dhldp', 'return', ['id_order_return' => $id_order_return], true)|escape:'html':'UTF-8'}" method="post" id="dhl_return">
                <section class="form-fields">
                    <div class="form-group row">
                        <label class="col-md-3 form-control-label">{l s='Name 1' mod='dhldp'}</label>
                        <div class="col-md-6">
                            <input class="form-control" type="text" name="address[name1]" maxlength="35" value="{if isset($smarty.post.address.name1)}{$smarty.post.address.name1|escape:'html':'UTF-8'}{else}{if isset($address.name1)}{$address.name1|escape:'html':'UTF-8'}{/if}{/if}" />
                        </div>
                        <div class="col-md-3 form-control-comment">
                            {l s='it will be printed on label' mod='dhldp'}
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-md-3 form-control-label">{l s='Name 2' mod='dhldp'}</label>
                        <div class="col-md-6">
                            <input class="form-control" type="text" name="address[name2]" maxlength="35" value="{if isset($smarty.post.address.name2)}{$smarty.post.address.name2|escape:'html':'UTF-8'}{else}{if isset($address.name2)}{$address.name2|escape:'html':'UTF-8'}{/if}{/if}" />
                        </div>
                        <div class="col-md-3 form-control-comment">
                            {l s='optional' mod='dhldp'}
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-md-3 form-control-label">{l s='Name 3' mod='dhldp'}</label>
                        <div class="col-md-6">
                            <input class="form-control" type="text" name="address[name3]" maxlength="35" value="{if isset($smarty.post.address.name3)}{$smarty.post.address.name3|escape:'html':'UTF-8'}{else}{if isset($address.name3)}{$address.name3|escape:'html':'UTF-8'}{/if}{/if}" />
                        </div>
                        <div class="col-md-3 form-control-comment">
                            {l s='optional' mod='dhldp'}
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-md-3 form-control-label">{l s='Street' mod='dhldp'}</label>
                        <div class="col-md-6">
                            <input class="form-control" type="text" name="address[streetName]" maxlength="35" value="{if isset($smarty.post.address.streetName)}{$smarty.post.address.streetName|escape:'html':'UTF-8'}{else}{if isset($address.streetName)}{$address.streetName|escape:'html':'UTF-8'}{/if}{/if}" />
                        </div>
                        <div class="col-md-3 form-control-comment">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-md-3 form-control-label">{l s='House number' mod='dhldp'}</label>
                        <div class="col-md-6">
                            <input class="form-control" type="text" name="address[houseNumber]" maxlength="5" value="{if isset($smarty.post.address.houseNumber)}{$smarty.post.address.houseNumber|escape:'html':'UTF-8'}{else}{if isset($address.houseNumber)}{$address.houseNumber|escape:'html':'UTF-8'}{/if}{/if}" />
                        </div>
                        <div class="col-md-3 form-control-comment">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-md-3 form-control-label">{l s='Postcode' mod='dhldp'}</label>
                        <div class="col-md-6">
                            <input class="form-control" type="text" name="address[postCode]" maxlength="10" value="{if isset($smarty.post.address.postCode)}{$smarty.post.address.postCode|escape:'html':'UTF-8'}{else}{if isset($address.postCode)}{$address.postCode|escape:'html':'UTF-8'}{/if}{/if}" />
                        </div>
                        <div class="col-md-3 form-control-comment">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-md-3 form-control-label">{l s='City' mod='dhldp'}</label>
                        <div class="col-md-6">
                            <input class="form-control" type="text" name="address[city]" maxlength="35" value="{if isset($smarty.post.address.city)}{$smarty.post.address.city|escape:'html':'UTF-8'}{else}{if isset($address.city)}{$address.city|escape:'html':'UTF-8'}{/if}{/if}" />
                        </div>
                        <div class="col-md-3 form-control-comment">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-md-3 form-control-label">{l s='State' mod='dhldp'}</label>
                        <div class="col-md-6">
                            <input class="form-control" type="text" name="address[country][state]" maxlength="30" value="{if isset($smarty.post.address.country.state)}{$smarty.post.address.country.state|escape:'html':'UTF-8'}{else}{if isset($address.country.state)}{$address.country.state|escape:'html':'UTF-8'}{/if}{/if}" />
                        </div>
                        <div class="col-md-3 form-control-comment">
                            {l s='optional' mod='dhldp'}
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-md-3 form-control-label">{l s='Country' mod='dhldp'}</label>
                        <div class="col-md-6">
                            <select name="address[country][countryISOCode]" class="form-control">
                                {foreach $countries as $country}
                                    <option value="{$country.iso_code|escape:'html':'UTF-8'}"{if isset($address.country.countryISOCode) && $address.country.countryISOCode == $country.iso_code} selected="selected"{else}{if isset($smarty.post.address.country.countryISOCode) && $smarty.post.address.country.countryISOCode == $country.iso_code} selected="selected"{/if}{/if}>{$country.name|escape:'html':'UTF-8'}</option>
                                {/foreach}
                            </select>
                        </div>
                        <div class="col-md-3 form-control-comment">
                        </div>
                    </div>
                </section>
                <footer class="form-footer clearfix">
                    {if isset($id_order_return)}<input type="hidden" name="id_order_return" value="{$id_order_return|intval}" />{/if}
                    <input type="hidden" name="token" value="{$token}" />
                    <button type="submit" name="submitCreateLabel" id="submitCreateLabel" class="btn btn-primary pull-xs-right">{l s='Create' mod='dhldp'}</button>
                </footer>
            </form>
        </div>
    {/if}
    {if isset($show_label) && ($show_label == true)}
        <div class="dhl-return-label-form">
        <h6>{l s='Return label has been generated. Please click on "Get return label" for downloading label PDF file.' mod='dhldp'}</h6>
        <form action="{$link->getModuleLink('dhldp', 'return', ['id_order_return' => $id_order_return], true)|escape:'html':'UTF-8'}" method="post" id="dhl_return">
            <footer class="form-footer clearfix">
                {if isset($id_order_return)}<input type="hidden" name="id_order_return" value="{$id_order_return|intval}" />{/if}
                <input type="hidden" name="token" value="{$token}" />
                <button type="submit" name="submitGetLabel" id="submitGetLabel" class="btn btn-primary">{l s='Get Return label' mod='dhldp'}</button>
            </footer>
        </form>
        </div>
    {/if}
    <ul class="footer_links clearfix">
        <li>
            <a class="btn btn-defaul button button-small" href="{$link->getPageLink('order-follow', true)|escape:'html':'UTF-8'}">
                <span><i class="icon-chevron-left"></i> {l s='Return Merchandise Authorization (RMA)' mod='dhldp'}</span>
            </a>
        </li>
    </ul>
{/block}