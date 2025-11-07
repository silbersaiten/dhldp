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
<div class="panel ">
    <div class="panel-heading">{l s='Manifest' mod='dhldp'}</div>
    <form action="{$currentIndex|escape:'html':'UTF-8'}&amp;manifest{$table|escape:'html':'UTF-8'}&amp;token={$token|escape:'html':'UTF-8'}" method="post" class="form-horizontal" id="getmanifest">
        <div class="alert alert-info">
            <p>{l s='With "Get manifest" operation a end-of-day  reports are available for a specific day or period.' mod='dhldp'}</p>
            <p>{l s='The DHL business customer portal automatically closes all stored shipments every day at 18:00 or you use "Do manifest" operation for every created label.' mod='dhldp'}</p>
        </div>
        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Manifest date:' mod='dhldp'}</label>
            <div class="col-lg-9">
                <input type="text" name="manifestDate" id="manifestDatepicker" value="{$manifestDate|escape}" class="datepicker fixed-width-sm">
            </div>
        </div>
        <div class="panel-footer">
            <button name="getManifest" type="submit" class="btn btn-primary">
                {l s='Get manifest' mod='dhldp'}
            </button>
        </div>
    </form>
</div>
<script type="text/javascript">
    $(document).ready(function() {
        if ($("form#getmanifest .datepicker").length > 0)
            $("form#getmanifest .datepicker").datepicker({
                prevText: '',
                nextText: '',
                dateFormat: 'yy-mm-dd'
            });
    });
</script>