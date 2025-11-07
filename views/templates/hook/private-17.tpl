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
<script type="text/javascript">
    var dhldp_carriers={$js_dhldp_carriers|json_encode nofilter};
    var dhldp_path='{$js_dhldp_path|escape:'javascript':'UTF-8'}';
</script>

<div class="row dhldp_permission_private" style="">
    <div class="col-md-12">
        <p class="title_dhldp_permission_private">{l s='Permission for transferring private data to DHL service' mod='dhldp'}</p>
        <p>
            <input type="checkbox" name="dhldp_permission_private" value="1"{if !empty($dhldp_permission_private) && $dhldp_permission_private == 1}checked="checked"{/if}> {l s='Yes, i give permission for transferring e-mail address and phone number to DHL service' mod='dhldp'}
        </p>
    </div>
</div>