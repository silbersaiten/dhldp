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
<script type="text/javascript">
    var dhldp_pfps_map = '{$dhldp_pfps_map|escape:'html':'UTF-8'}';
    var dhldp_address_data = {$dhldp_address_data|json_encode nofilter};
    var dhldp_country_data = {$dhldp_country_data|json_encode nofilter};
    var dhldp_ajax = '{$dhldp_ajax|escape:'html':'UTF-8'}';
    var dhldp_path = '{$dhldp_path|escape:'html':'UTF-8'}';

    {strip}
    var dhldp_trans = {
        'select': '{l s='Select' mod='dhldp'}',
        'select_close': '{l s='Select and close' mod='dhldp'}',
        'packstation': '{l s='Packstation' mod='dhldp'}',
        'postfiliale': '{l s='Postfiliale' mod='dhldp'}'
    }
    {/strip}
</script>