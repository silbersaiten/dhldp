{**
* DHL Deutschepost
*
* @author    silbersaiten <info@silbersaiten.de>
* @copyright 2026 silbersaiten
* @license   See joined file licence.txt
* @category  Module
* @support   silbersaiten <support@silbersaiten.de>
* @version   3.2.6
* @link      https://www.silbersaiten.de
*}
<div>
    {if $general_log_file_path}
        <a class="btn button btn-default" href="{$general_log_file_path|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{l s='Download General log file' mod='dhldp'}</a>
    {/if}
    {if $api_log_file_path}
        <a class="btn button btn-default" href="{$api_log_file_path|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{l s='Download API log file' mod='dhldp'}</a>
    {/if}
    <a class="btn button btn-default" href="{$api_log_file_path_clear|escape:'html':'UTF-8'}">{l s='Clear API log file' mod='dhldp'}</a>
</div>
