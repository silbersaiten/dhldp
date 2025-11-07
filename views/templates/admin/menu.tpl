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
<nav class="menu-dhldp navbar navbar-default">
    <div class="container-fluid">
        <ul class="nav navbar-nav menu">
            {foreach $menu_items as $menu_item_key => $menu_item}
                <li{if $menu_item.active == true} class="active"{/if}><a href="{$menu_item.url|escape:'html':'UTF-8'}">{$menu_item.name|escape:'htmlall':'UTF-8'}{if $menu_item.icon != ''} <i class="{$menu_item.icon|escape:'htmlall':'UTF-8'}"></i>{/if}</a></li>
            {/foreach}
        </ul>
        <ul class="nav navbar-nav navbar-right info">
            <li><a href="#" rel="nofollow">{$module_name|escape:'htmlall':'UTF-8'} {l s='Version' mod='dhldp'}: {$module_version|escape:'htmlall':'UTF-8'}</a></li>
            {if $changelog == true}
                <li><a href="{$changelog_path|escape:'htmlall':'UTF-8'}" class="readme-fancybox">{l s='Changelog' mod='dhldp'}</a></li>
            {/if}
            <li><a href="https://addons.prestashop.com/de/contact-us?id_product=20569" target="_blank">{l s='contact us' mod='dhldp'}</a></li>
            <li><a href="https://addons.prestashop.com/de/20_silbersaiten" target="_blank">{l s='our modules' mod='dhldp'}</a></li>
        </ul>
    </div>
</nav>
<script type="text/javascript">
    $(document).ready(function() {
        $('.readme-fancybox').fancybox({
            type: 'iframe',
            autoDimensions: false,
            autoSize: false,
            width: 600,
            height: 'auto',
            helpers: {
                overlay: {
                    locked: false
                }
            }
        });
    });
</script>
