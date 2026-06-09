{**
* DHL Deutschepost
*
* @author    silbersaiten <info@silbersaiten.de>
* @copyright 2026 silbersaiten
* @license   See joined file licence.txt
* @category  Module
* @support   silbersaiten <support@silbersaiten.de>
* @version   3.2.0
* @link      https://www.silbersaiten.de
*}
<form method="post" action="{$multishop_activation_action|escape:'html':'UTF-8'}">
    <input type="hidden" name="submitMultishopActivation" value="1" />
    <section class="panel">
        <h3><i class="icon-cogs"></i> {l s='Configuration' mod='dhldp'}</h3>
        <div class="form-wrapper">
            <div class="checkbox">
                <label>
                    <input type="hidden" name="activateModule" value="0" />
                    <input type="checkbox" name="activateModule" value="1"{if $multishop_activation_enabled} checked="checked"{/if} onchange="this.form.submit();" />
                    {l s='Activate module for this shop context' mod='dhldp'}: {$multishop_activation_context_label nofilter}.
                </label>
            </div>
        </div>
    </section>
</form>
