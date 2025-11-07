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
<div class="row dhldp_dp_panel">
	<div class="col-lg-12">
		<div class="panel dhldp_dp_order_panel{if $is177} card card-body{/if}">
			<div class="panel-heading{if $is177} card-header{/if}">
				<i class="icon-truck"></i> {l s='Deutschepost delivery labels' mod='dhldp'} <span class="pull-right">{$module_name|escape:'htmlall':'UTF-8'} v.{$module_version|escape:'htmlall':'UTF-8'}</span>
			</div>
		
			{if isset($deutschepost_errors) && $deutschepost_errors|@count}
				{foreach from=$deutschepost_errors item=error}
			<p class="alert alert-danger">{$error|escape:'htmlall':'UTF-8'}</p>
				{/foreach}
			{/if}

			{if isset($labels) && is_array($labels) && count($labels)}
			<button class="btn btn-default" name="showAllDPLabels" id="showAllDPLabels"><i class="icon-history"></i> {l s='Show all labels' mod='dhldp'} ({count($labels)|escape:'htmlall':'UTF-8'})</button>
			<div id="allDPLabels" style="display:none;">
				<div class="table-responsive">
					<table class="table std" cellspacing="0" cellpadding="0">
						<thead>
							<tr>
								<th>{l s='ID' mod='dhldp'}</th>
								<th>{l s='Date' mod='dhldp'}</th>
								<th>{l s='Product' mod='dhldp'}</th>
								<th>{l s='Information' mod='dhldp'}</th>
								<th>{l s='Action' mod='dhldp'}</th>
							</tr>
						</thead>
						<tbody>
							{foreach from=$labels item=label}
							<tr>
								<td>{$label.id_dhldp_dp_label|escape:'htmlall':'UTF-8'}</td>
								<td>{$label.date_add|escape:'htmlall':'UTF-8'}</td>
								<td>{$label.product|escape:'htmlall':'UTF-8'}<br>
									{$label.product_name|escape:'htmlall':'UTF-8'}<br>
								</td>
								<td>
									{l s='Voucher ID' mod='dhldp'}: {$label.dp_voucher_id|escape:'htmlall':'UTF-8'}<br>
                                    {if $label.dp_track_id != ''}{l s='Track ID' mod='dhldp'}: {$label.dp_track_id|escape:'htmlall':'UTF-8'}<br>{/if}
									{l s='Order ID' mod='dhldp'}: {$label.dp_order_id|escape:'htmlall':'UTF-8'}<br>
									{l s='Total' mod='dhldp'}: {displayPrice price=$label.total}<br>
									{l s='Remained wallet ballance' mod='dhldp'}: {displayPrice price=$label.wallet_ballance}<br>
									{l s='Note' mod='dhldp'}: {$label.additional_info|escape:'htmlall':'UTF-8'}<br>
                                    {l s='Label format' mod='dhldp'}: {$label.label_format|escape:'htmlall':'UTF-8'}<br>
                                    {if $label.label_format == 'pdf'}
                                        {l s='Page format' mod='dhldp'}: {$label.page_format_name|escape:'htmlall':'UTF-8'}<br>
                                        {l s='Label position' mod='dhldp'}: {$label.label_position_name|escape:'htmlall':'UTF-8'}<br>
                                    {/if}
								</td>
								<td>
									<a class="btn btn-primary btn-sm" href="{$label.dp_link|escape:'htmlall':'UTF-8'}" target="_blank"><i class="icon-print"></i> {l s='Print label' mod='dhldp'}</a>
                                    {if $label.manifest_link != ''}<a class="btn btn-primary btn-sm" href="{$label.manifest_link|escape:'htmlall':'UTF-8'}" target="_blank"><i class="icon-info"></i> {l s='Manifest / Shipping list' mod='dhldp'}</a>{/if}
									{if $label.dp_track_link != ''}<a class="btn btn-primary btn-sm" href="{$label.dp_track_link|escape:'htmlall':'UTF-8'}" target="_blank"><i class="icon-info"></i> {l s='Tracking' mod='dhldp'}</a>{/if}
								</td>
							</tr>
							{/foreach}
						</tbody>
					</table>
				</div>
			</div>
			{/if}

			<form method="post" action="{$form_action|escape:'htmlall':'UTF-8'}" class="form-inline">
				<input type="hidden" name="id_order_carrier" value="{$carrier.id_order_carrier|escape:'htmlall':'UTF-8'}" />
				<input type="hidden" name="id_carrier" value="{$carrier.id_carrier|escape:'htmlall':'UTF-8'}" />
				<input type="hidden" name="id_address" value="{$id_address|escape:'htmlall':'UTF-8'}" />

				<div class="table-responsive">
					<table class="table std" cellspacing="0" cellpadding="0">
						<thead>
							<tr>
								<th>{l s='Carrier' mod='dhldp'}</th>
								<th>{l s='Product' mod='dhldp'}</th>
								<th>{l s='Note' mod='dhldp'}</th>
                                <th>{l s='Label print options' mod='dhldp'}</th>
								<th>{l s='Actions' mod='dhldp'}</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td>{$carrier.carrier_name|escape:'htmlall':'UTF-8'}
								</td>
								<td>
									<select name="product" class="fixed-width-lg {if $is177}custom-select{/if}" style="display: inline-block;">
									{foreach from=$deutcshepost_products key=deutcshepost_product_index item=deutcshepost_product}
										<option value="{$deutcshepost_product.code|escape:'htmlall':'UTF-8'}"{if ((isset($last_label.product) && $deutcshepost_product.code == $last_label.product) || (!isset($last_label.product) && $predef_deutschepost_product==$deutcshepost_product.code))} selected{/if}>{$deutcshepost_product.name|escape:'htmlall':'UTF-8'}
 - {$deutcshepost_product.price|escape:'htmlall':'UTF-8'} Euro</option>
									{/foreach}
									</select>
								</td>
								<td>
									<textarea class="form-control" name="additional_info">{if isset($last_label.additional_info)}{$last_label.additional_info|escape:'htmlall':'UTF-8'}{/if}</textarea> <br>{l s='Max. 80 characters' mod='dhldp'}
								</td>
                                <td>
                                    {l s='Label format' mod='dhldp'} : {$def_label_format|escape:'htmlall':'UTF-8'}<br>
                                    {if $def_label_format == 'pdf'}
                                        {l s='Page format' mod='dhldp'} : {$def_page_format_name|escape:'htmlall':'UTF-8'}<br>
                                        {l s='Label position' mod='dhldp'} :<br>
                                                {if isset($smarty.post.label_position_page)}
                                                    {assign var=sel_label_format_page value=$smarty.post.label_position_page}
                                                    {assign var=sel_label_format_col value=$smarty.post.label_position_col}
                                                    {assign var=sel_label_format_row value=$smarty.post.label_position_row}
                                                {elseif isset($last_label.label_format) && $last_label.label_format == 'pdf'}
                                                    {assign var=sel_label_format_page value=$last_label.label_position_detail.page}
                                                    {assign var=sel_label_format_col value=$last_label.label_position_detail.col}
                                                    {assign var=sel_label_format_row value=$last_label.label_position_detail.row}
                                                {else}
                                                    {assign var=sel_label_format_page value=$def_label_position_page}
                                                    {assign var=sel_label_format_col value=$def_label_position_col}
                                                    {assign var=sel_label_format_row value=$def_label_position_row}
                                                {/if}
                                                <table  class="table std" cellspacing="0" cellpadding="0">
                                                    <tr>
                                                        <td>{l s='Page' mod='dhldp'}</td>
                                                        <td><input class="form-control" type="text" name="label_position_page" class="fixed-width-xs" value="{$sel_label_format_page|escape:'htmlall':'UTF-8'}"></td>
                                                    </tr>
                                                    <tr>
                                                        <td>{l s='Column' mod='dhldp'}</td>
                                                        <td><input class="form-control" type="text" name="label_position_col" class="fixed-width-xs" value="{$sel_label_format_col|escape:'htmlall':'UTF-8'}"></td>
                                                    </tr>
                                                    <tr>
                                                        <td>{l s='Row' mod='dhldp'}</td>
                                                        <td><input class="form-control" type="text" name="label_position_row" class="fixed-width-xs" value="{$sel_label_format_row|escape:'htmlall':'UTF-8'}"></td>
                                                    </tr>
                                                </table>
                                    {/if}
                                </td>
								<td>
									{if isset($last_label['id_dhldp_dp_label'])}
										{l s='Voucher ID' mod='dhldp'}: {$last_label.dp_voucher_id|escape:'htmlall':'UTF-8'}<br>
                                        {if $last_label.dp_track_id != ''}{l s='Track ID' mod='dhldp'}: {$last_label.dp_track_id|escape:'htmlall':'UTF-8'}<br>{/if}
										{l s='Order ID' mod='dhldp'}: {$last_label.dp_order_id|escape:'htmlall':'UTF-8'}<br>
										{l s='Total' mod='dhldp'}: {displayPrice price=$last_label.total}<br>
										{l s='Remained wallet ballance' mod='dhldp'}: {displayPrice price=$last_label.wallet_ballance}<br>
										{l s='Note' mod='dhldp'}: {$last_label.additional_info|escape:'htmlall':'UTF-8'}<br>

										<a class="btn btn-primary btn-sm" href="{$last_label.dp_link|escape:'htmlall':'UTF-8'}" target="_blank"><i class="icon-print"></i> {l s='Print label' mod='dhldp'}</a>
                                        {if $last_label.manifest_link != ''}<a class="btn btn-primary btn-sm" href="{$last_label.manifest_link|escape:'htmlall':'UTF-8'}" target="_blank"><i class="icon-info"></i> {l s='Manifest / Shipping list' mod='dhldp'}</a>{/if}
										{if $last_label.dp_track_link != ''}<a class="btn btn-primary btn-sm" href="{$last_label.dp_track_link|escape:'htmlall':'UTF-8'}" target="_blank"><i class="icon-info"></i> {l s='Tracking' mod='dhldp'}</a>{/if}
										<input type="submit" class="btn btn-success btn-sm" name="submitDPLabelRequest" value="{l s='Generate new label' mod='dhldp'}" />
										{*<button type="submit" class="btn btn-danger" name="submitDeutschepostLabelReturnRequest"><i class="icon-reply"></i> {l s='Return label' mod='dhldp'}</button>*}
									{else}
										<input type="submit" class="btn btn-success btn-sm" name="submitDPLabelRequest" value="{l s='Generate label' mod='dhldp'}" />
									{/if}
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</form>
		</div>
	</div>
</div>