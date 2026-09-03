{**
 * Local documentation and support links for the DHL Deutschepost settings pages.
 *}
<section class="panel dhldp-quick-start" aria-labelledby="dhldp-quick-start-title">
    <h3 id="dhldp-quick-start-title"><i class="icon-rocket" aria-hidden="true"></i> {$dhldp_quick_start_title|escape:'htmlall':'UTF-8'}</h3>
    <p>{$dhldp_quick_start_intro|escape:'htmlall':'UTF-8'}</p>
    <ol class="dhldp-quick-start__steps">
        {foreach $dhldp_quick_start_steps as $dhldp_quick_start_step}
            <li>{$dhldp_quick_start_step|escape:'htmlall':'UTF-8'}</li>
        {/foreach}
    </ol>

    <div class="dhldp-quick-start__separator" aria-hidden="true"></div>
    <div class="dhldp-quick-start__buttons" aria-label="{$dhldp_quick_start_title|escape:'htmlall':'UTF-8'}">
        <a class="btn btn-default" href="{$dhldp_user_guide_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">
            <i class="icon-book" aria-hidden="true"></i> {$dhldp_user_guide_label|escape:'htmlall':'UTF-8'}
        </a>
        <a class="btn btn-default" href="{$dhldp_module_description_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">
            <i class="icon-file-text" aria-hidden="true"></i> {$dhldp_module_description_label|escape:'htmlall':'UTF-8'}
        </a>
    </div>
    <div class="dhldp-quick-start__buttons dhldp-quick-start__service-buttons">
        <a class="btn btn-default" href="{$dhldp_more_modules_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">
            <i class="icon-puzzle-piece" aria-hidden="true"></i> {$dhldp_more_modules_label|escape:'htmlall':'UTF-8'}
        </a>
        <a class="btn btn-default" href="{$dhldp_paid_support_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">
            <i class="icon-life-ring" aria-hidden="true"></i> {$dhldp_paid_support_label|escape:'htmlall':'UTF-8'}
        </a>
        <a class="btn btn-default" href="{$dhldp_support_email_url|escape:'html':'UTF-8'}">
            <i class="icon-envelope" aria-hidden="true"></i> {$dhldp_support_email_label|escape:'htmlall':'UTF-8'}
        </a>
    </div>
</section>
