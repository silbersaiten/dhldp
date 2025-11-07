{**

* DHL Deutschepost

*

* @author    silbersaiten <info@silbersaiten.de>

* @copyright 2025 silbersaiten

* @license   See joined file licence.txt

* @category  Module

* @support   silbersaiten <support@silbersaiten.de>

* @version   1.1.0

* @link      http://www.silbersaiten.de

*}

<script type="text/javascript">

	var dhldp_ajax_path = '{$dhldp_ajax_path}';

	var dhldp_dhl_products_params={$dhldp_dhl_products_params|json_encode};

	var dhldp_cur_dhl_product_params={};
	window.dhlProductDimensions = {$dhldp_dhl_products_params|json_encode};

</script>

{capture assign=priceDisplayPrecisionFormat}{'%.'|cat:$smarty.const._PS_PRICE_DISPLAY_PRECISION_|cat:'f'|escape:'htmlall':'UTF-8'}{/capture}

<div class="row dhldp_dhl_order_block">

	<div class="col-lg-12">

		<div class="panel dhl_order_panel{if $is177} card card-body{/if}" >

			<div class="panel-heading{if $is177} card-header{/if}">

				<i class="icon-truck"></i> {l s='DHL Delivery Labels' mod='dhldp'} <span class="pull-right">{$module_name|escape:'htmlall':'UTF-8'} v.{$module_version|escape:'htmlall':'UTF-8'}</span>

			</div>



			{if isset($dhl_errors) && $dhl_errors|@count}

				<div class="alert alert-danger"><ul>

						{foreach from=$dhl_errors item=dhl_error}

							<li>{$dhl_error|escape:'htmlall':'UTF-8'}</li>

						{/foreach}

					</ul></div>

			{/if}



			{if isset($dhl_confirmations) && $dhl_confirmations|@count}

				<div class="alert alert-success"><ul>

						{foreach from=$dhl_confirmations item=dhl_confirmation}

							<li>{$dhl_confirmation|escape:'htmlall':'UTF-8'}</li>

						{/foreach}

					</ul></div>

			{/if}

			{if isset($dhl_warnings) && $dhl_warnings|@count}
				<div class="alert alert-warning">
					{if isset($dhl_confirmations) && $dhl_confirmations|@count}
						{if isset($dhl_warnings_title)}
							<p class="mb-1">
								<strong>{$dhl_warnings_title|escape:'htmlall':'UTF-8'}</strong>
							</p>
						{/if}
					{else}
						{if isset($dhl_with_warning)}
							{if $dhl_with_warning == false}
								<div class="form-group">
									<label class="control-label">
										<strong>{l s='Create a label anyway' mod='dhldp'}</strong>
									</label>
									<input type="checkbox" id="submitDHLDPDhlCreateLabelNoValidation" value="">
								</div>
							{/if}
						{/if}
					{/if}

					<ul>
						{foreach from=$dhl_warnings item=dhl_warning}
							<li>{$dhl_warning|escape:'htmlall':'UTF-8'}</li>
						{/foreach}
					</ul>
				</div>
			{/if}

			{if isset($labels) && is_array($labels) && count($labels)}

				<button data-icon-more="{if $is177}expand_more{else}icon-angle-double-down{/if}" data-icon-less="{if $is177}expand_less{else}icon-angle-double-up{/if}" class="btn btn-default btn-sm" name="showAllDHLDPDhlLabels" id="showAllDHLDPDhlLabels">{if $is177}<i class="material-icons">expand_more</i>{else}<i class="icon-angle-double-down"></i>{/if} {l s='Show all labels' mod='dhldp'} ({count($labels)|escape:'htmlall':'UTF-8'})</button>

				<div id="sectionAllDHLDPDhlLabels" style="display:none;">

					<div class="table-responsive">

						<table class="table std">

							<thead>

							<tr>

								<th>{l s='ID' mod='dhldp'}</th>

								<th>{l s='Date' mod='dhldp'}</th>

								<th>{l s='Product / Shipment date' mod='dhldp'}</th>

								<th>{l s='Parameters' mod='dhldp'}</th>

								<th>{l s='Services' mod='dhldp'}</th>

								<th>{l s='Shipment number' mod='dhldp'}</th>

								<th>{l s='Action' mod='dhldp'}</th>

							</tr>

							</thead>

							<tbody>

							{foreach from=$labels item=label}

								<tr>

									<td>{$label.id_dhldp_label|escape:'htmlall':'UTF-8'}</td>

									<td>{$label.date_add|escape:'htmlall':'UTF-8'}</td>

									<td>

										{$label.product_name|escape:'htmlall':'UTF-8'}<br>

										{if isset($label.is_return) && $label.is_return == 1}

											<span class="badge badge-warning">{l s='Return label' mod='dhldp'}</span><br>

										{/if}

										{if isset($label.with_return) && $label.with_return == 1}

											<span class="badge badge-warning">{l s='With return label' mod='dhldp'}</span><br>

										{/if}

										{if $label.shipment_date != '0000-00-00 00:00:00'}

											{strftime('%Y-%m-%d', strtotime($label.shipment_date))}

										{/if}

									</td>

									<td>

										<label>{l s='Weight' mod='dhldp'}</label>: {Tools::ps_round($label.packages[0]['weight'], 1)|escape:'htmlall':'UTF-8'} {l s='kg' mod='dhldp'}<br>

										<label>{l s='Length' mod='dhldp'}</label>: {$label.packages[0]['length']|escape:'htmlall':'UTF-8'} {l s='cm' mod='dhldp'}<br>

										<label>{l s='Width' mod='dhldp'}</label>: {$label.packages[0]['width']|escape:'htmlall':'UTF-8'} {l s='cm' mod='dhldp'}<br>

										<label>{l s='Height' mod='dhldp'}</label>: {$label.packages[0]['height']|escape:'htmlall':'UTF-8'} {l s='cm' mod='dhldp'}<br>

									</td>

									<td>

										<div id="dhl_options">

											{if isset($label.options_decoded['DeclaredValueOfGoods'])}

												<div>

													<label>{l s='Declared value' mod='dhldp'}</label>

													<div class="input-group fixed-width-x2 pull-right">

														{$label.options_decoded['DeclaredValueOfGoods']|escape:'htmlall':'UTF-8'} {$label.options_decoded['DeclaredValueOfGoodsCurrency']|escape:'htmlall':'UTF-8'}

													</div>

												</div>

											{/if}

											{if isset($label.options_decoded['COD']['CODAmount'])}

												<div>

													<label>{l s='COD amount' mod='dhldp'}</label>

													<div class="input-group fixed-width-x2 pull-right">

														{$label.options_decoded['COD']['CODAmount']|escape:'htmlall':'UTF-8'} {$label.options_decoded['COD']['CODCurrency']|escape:'htmlall':'UTF-8'}

													</div>

												</div>

											{/if}

											{if isset($label.options_decoded['HigherInsurance']['InsuranceAmount'])}

												<div>

													<label>{l s='Insurance amount' mod='dhldp'}</label>

													<div class="input-group fixed-width-x2 pull-right">

														{$label.options_decoded['HigherInsurance']['InsuranceAmount']|escape:'htmlall':'UTF-8'} {$label.options_decoded['HigherInsurance']['InsuranceCurrency']|escape:'htmlall':'UTF-8'}

													</div>

												</div>

											{/if}

											{if isset($label.options_decoded['CheckMinimumAge']['MinimumAge']) && $label.options_decoded['CheckMinimumAge']['MinimumAge'] > 0}

												<div>

													<label>{l s='Check minimum age' mod='dhldp'}</label>

													<div class="input-group fixed-width-x2 pull-right">

														{$label.options_decoded['CheckMinimumAge']['MinimumAge']|escape:'htmlall':'UTF-8'} {l s='years' mod='dhldp'}

													</div>

												</div>

											{/if}

										</div>

									</td>

									<td>

										{$label.shipment_number|escape:'htmlall':'UTF-8'}

									</td>

									<td>

										<form method="post" action="{$form_action|escape:'htmlall':'UTF-8'}" class="form-inline">

											<a class="btn btn-primary btn-sm" href="{$label.label_url|escape:'html':'UTF-8'}" target="_blank"><i class="icon-print"></i> {l s='Print label' mod='dhldp'}</a>

											{if $label.return_label_url != ''}

												<a class="btn btn-primary btn-sm" href="{$label.return_label_url|escape:'html':'UTF-8'}" target="_blank"><i class="icon-print"></i> {l s='Print return label' mod='dhldp'}</a>

											{/if}

											{if $label.export_label_url != ''}

												<a class="btn btn-primary btn-sm" href="{$label.export_label_url|escape:'html':'UTF-8'}" target="_blank"><i class="icon-print"></i> {l s='Print export doc' mod='dhldp'}</a>

											{/if}

											{if $label.cod_label_url != ''}

												<a class="btn btn-primary btn-sm" href="{$label.cod_label_url|escape:'html':'UTF-8'}" target="_blank"><i class="icon-print"></i> {l s='Print COD doc' mod='dhldp'}</a>

											{/if}

											<input type="hidden" name="shipment_number" value="{$label.shipment_number|escape:'htmlall':'UTF-8'}" />

											{if $label.product_code != 'rp'}

												{if $DHLDP_DHL_CREATE_MANIFEST_IN_ORDER}
													<input type="submit" class="btn btn-default btn-sm" name="doDHLDPDhlManifest" value="{l s='Do manifest' mod='dhldp'}">
												{/if}

												<input type="submit" class="btn btn-danger btn-sm" name="deleteDHLDPDhlLabel" value="{l s='Delete label' mod='dhldp'}">

											{/if}

											<a class="btn btn-default btn-sm" href="{$label.tracking_url|escape:'html':'UTF-8'}" target="_blank"><i class="icon-search"></i> {l s='Tracking' mod='dhldp'}</a>

											<br>

											<a class="btn btn-default dhldp-get-tracking-data btn-sm" href="#" data-tracking-number="{$label.shipment_number|escape:'html':'UTF-8'}"><i class="icon-truck"></i> {l s='Update tracking data' mod='dhldp'}</a>

											<div class="dhldp-last-tracking-data"></div>

										</form>

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

				<input type="hidden" id="validation_of_dhl_label_creation" name="validation_of_dhl_label_creation" value="">

				<div class="table-responsive">

					<table class="table std" cellspacing="0" cellpadding="0">

						<thead>

						<tr>

							<th>{l s='Carrier' mod='dhldp'}</th>

							<th>{l s='Product / Shipment date' mod='dhldp'}</th>

							<th>{l s='Parameters and Services' mod='dhldp'}</th>

							<th>{l s='Actions' mod='dhldp'}</th>

						</tr>

						</thead>

						<tbody>

						<tr>

							<td>{$carrier.carrier_name|escape:'htmlall':'UTF-8'}

							</td>

							<td>

								<div class="{if !$is177}row{/if}">

									<select id="dhldp_dhl_product_code" name="dhl_product_code" class="fixed-width-lg{if $is177} custom-select{/if}" style="display: inline-block;">

										{foreach from=$dhl_products key=dhl_product_index item=dhl_product}

											<option value="{$dhl_product.fullcode|escape:'htmlall':'UTF-8'}"{if isset($smarty.post.dhl_product_code) && $dhl_product.fullcode == $smarty.post.dhl_product_code} selected="selected"{elseif ((isset($carrier.default_dhl_product_code) && $dhl_product.fullcode == $carrier.default_dhl_product_code))} selected="selected"{elseif ((isset($last_label.product_code) && $dhl_product.fullcode == $last_label.product_code))} selected="selected"{/if}>{$dhl_product.fullname|escape:'htmlall':'UTF-8'}</option>

										{/foreach}

									</select>

								</div>

								<div class="{if !$is177}row{/if}">

									{if $is177}<div class="input-group datepicker">{/if}

										<input class="form-control{if $is177} custom-select{else} datepicker{/if}" type="text" name="dhl_shipment_date"

											   value="{if isset($smarty.post.dhl_shipment_date)}{$smarty.post.dhl_shipment_date|escape:'htmlall':'UTF-8'}{else}{$shipment_date|escape:'htmlall':'UTF-8'}{/if}" maxlength="10"/>

										{if $is177}</div>{/if}

									<span class="help-block">{l s='yyyy-mm-dd' mod='dhldp'}</span>

								</div>

							</td>

							<td>

								<div id="dhldp_dhl_params" class="row">

									<div class="col-lg-6">

										<div class="row">

											<label for="dhl_weight_package" class="control-label col-lg-3 required">{l s='Weight' mod='dhldp'}</label>

											<div class="col-lg-9">

												<div class="input-group{if $is177} money-type{else} fixed-width-x2 pull-right{/if}">

													{if $is177}

														<div class="input-group-prepend">

															<span class="input-group-text">{l s='kg' mod='dhldp'}</span>

														</div>

													{/if}

													<input type="text" class="parcel_weight{if $is177} form-control{/if}" id="dhl_weight_package" name="dhl_weight_package" size="3" value="{if isset($smarty.post.dhl_weight_package)}{$smarty.post.dhl_weight_package|escape:'htmlall':'UTF-8'}{elseif isset($last_label.product_code)}{Tools::ps_round($last_label.packages[0]['weight'], 1)|escape:'htmlall':'UTF-8'}{else}{$total_weight|escape:'htmlall':'UTF-8'}{/if}" />

													{if !$is177}

														<div class="input-group-addon">{l s='kg' mod='dhldp'}</div>

													{/if}

												</div>

												<span class="help-block"></span>

											</div>

										</div>

										<div class="row">

											<label for="dhl_length" class="control-label col-lg-3">{l s='Length' mod='dhldp'}</label>

											<div class="col-lg-9">

												<div class="input-group{if $is177} money-type{else} fixed-width-x2 pull-right{/if}">

													{if $is177}

														<div class="input-group-prepend">

															<span class="input-group-text">{l s='cm' mod='dhldp'}</span>

														</div>

													{/if}

													<input type="text" class="{if $is177}form-control{/if}" size="3" id="dhl_length" name="dhl_length" value="{if isset($smarty.post.dhl_length)}{$smarty.post.dhl_length|escape:'htmlall':'UTF-8'}{elseif isset($last_label.product_code)}{$last_label.packages[0]['length']|escape:'htmlall':'UTF-8'}{else}{$package_length|escape:'htmlall':'UTF-8'}{/if}" />

													{if !$is177}

														<div class="input-group-addon">{l s='cm' mod='dhldp'}</div>

													{/if}

												</div>

												<span class="help-block"></span>

											</div>

										</div>

									</div>

									<div class="col-lg-6">

										<div class="row">

											<label for="dhl_width" class="control-label col-lg-3">{l s='Width' mod='dhldp'}</label>

											<div class="col-lg-9">

												<div class="input-group{if $is177} money-type{else} fixed-width-x2 pull-right{/if}">

													{if $is177}

														<div class="input-group-prepend">

															<span class="input-group-text">{l s='cm' mod='dhldp'}</span>

														</div>

													{/if}

													<input type="text" class="{if $is177}form-control{/if}" size="3" id="dhl_width" name="dhl_width" value="{if isset($smarty.post.dhl_width)}{$smarty.post.dhl_width|escape:'htmlall':'UTF-8'}{elseif isset($last_label.product_code)}{$last_label.packages[0]['width']|escape:'htmlall':'UTF-8'}{else}{$package_width|escape:'htmlall':'UTF-8'}{/if}" />

													{if !$is177}

														<div class="input-group-addon">{l s='cm' mod='dhldp'}</div>

													{/if}

												</div>

												<span class="help-block"></span>

											</div>

										</div>

										<div class="row">

											<label for="dhl_height" class="control-label col-lg-3">{l s='Height' mod='dhldp'}</label>

											<div class="col-lg-9">

												<div class="input-group{if $is177} money-type{else} fixed-width-x2 pull-right{/if}">

													{if $is177}

														<div class="input-group-prepend">

															<span class="input-group-text">{l s='cm' mod='dhldp'}</span>

														</div>

													{/if}

													<input type="text" class="{if $is177}form-control{/if}" size="3" id="dhl_height" name="dhl_height" value="{if isset($smarty.post.dhl_height)}{$smarty.post.dhl_height|escape:'htmlall':'UTF-8'}{elseif isset($last_label.product_code)}{$last_label.packages[0]['height']|escape:'htmlall':'UTF-8'}{else}{$package_height|escape:'htmlall':'UTF-8'}{/if}" />

													{if !$is177}

														<div class="input-group-addon">{l s='cm' mod='dhldp'}</div>

													{/if}

												</div>

												<span class="help-block"></span>

											</div>

										</div>

									</div>

								</div>

								<div class="dhldp_dhl_addit_services_inline">

									<div class="form-group-flex">

										<label class="control-label col-lg-6">{l s='Parcel outlet routing' mod='dhldp'}

											<span class="help-box{if $is16}-f{/if}" data-toggle="popover" data-content="{l s='Your undeliverable item (recipient unknown or acceptance refused) gets a second chance to reach the recipient. Rather than being returned immediately to you, the undeliverable item will be held at the nearest retail outlet that has a parcel collection point for collection by the recipient. Your recipient will be informed of this by e-mail. If the item is collected, the time and costs involved in returning it can be avoided' mod='dhldp'}"></span>

										</label>

										<div class="col-lg-6">

											<input class="form-control" type="checkbox" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][ParcelOutletRouting]"

													{if $addit_services.ParcelOutletRouting == 1}{if is_array($permission_confirmation) && !$permission_confirmation['permission_tpd']}{else} checked="checked"{/if}{/if}

												   value="1"{if is_array($permission_confirmation) && !$permission_confirmation['permission_tpd']} disabled="disabled"{/if}/>

										</div>

									</div>

									<div class="form-group-flex">

										<label class="control-label col-lg-6">{l s='Parcel outlet routing: details' mod='dhldp'}

											<span class="help-box{if $is16}-f{/if}" data-toggle="popover" data-content="{l s='Details can be an email-address, if not set receiver email will be used' mod='dhldp'}"></span>

										</label>

										<div class="col-lg-6">
											<input type="email" class="form-control" style="width: 100%;" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][ParcelOutletRouting_details]" value="{$addit_services.ParcelOutletRouting_details|escape:'htmlall':'UTF-8'}" />

											{*				<textarea class="form-control" style="width: 100%;word-wrap: break-word; resize: none; height: 85px;" name="addit_services[{$addit_services.id_order_carrier|escape:'html':'UTF-8'}][ParcelOutletRouting_details]"*}

											{*						  maxlength="100">{$addit_services.ParcelOutletRouting_details|escape:'htmlall':'UTF-8'}</textarea>*}

											<span class="help-block"></span>

										</div>

									</div>

									{if is_array($permission_confirmation)}

										<div class="form-group-flex">

											{if $permission_confirmation['permission_tpd']}

												<div class="col-lg-6"></div>

												<div class="col-lg-6 alert alert-success">

													{l s='Permission for transferring e-mail address and phone number has been granted by customer' mod='dhldp'} ({$permission_confirmation['date_add']})

												</div>

											{else}

												<div class="col-lg-6"></div>

												<div class="col-lg-6 alert alert-warning">

													{l s='Permission for transferring e-mail address and phone number has NOT been granted by customer' mod='dhldp'} ({$permission_confirmation['date_add']})

												</div>

											{/if}

										</div>

									{/if}

								</div>

				</div>

				<div class="row param-footer">

					<div class="col-lg-12">

						<button type="button" data-icon-more="{if $is177}expand_more{else}icon-angle-double-down{/if}" data-icon-less="{if $is177}expand_less{else}icon-angle-double-up{/if}" class="button btn btn-default btn-sm pull-right" id="submitDHLDPDhlAdditServices" name="submitDHLDPDhlAdditServices">{if $is177}<i class="material-icons">expand_more</i>{else}<i class="icon-angle-double-down"></i>{/if} {l s='Additional services' mod='dhldp'} </button>

						<button type="button" data-icon-more="{if $is177}expand_more{else}icon-angle-double-down{/if}" data-icon-less="{if $is177}expand_less{else}icon-angle-double-up{/if}" class="button btn btn-default btn-sm" id="submitDHLDPDhlUpdateAddress" name="submitDHLDPDhlUpdateAddress">{if $is177}<i class="material-icons">expand_more</i>{else}<i class="icon-angle-double-down"></i>{/if} {l s='Update delivery address' mod='dhldp'} </button>

						<button type="button" data-icon-more="{if $is177}expand_more{else}icon-angle-double-down{/if}" data-icon-less="{if $is177}expand_less{else}icon-angle-double-up{/if}" class="button btn btn-default btn-sm" id="submitDHLDPDhlExportDocuments" name="submitDHLDPDhlExportDocuments">{if $is177}<i class="material-icons">expand_more</i>{else}<i class="icon-angle-double-down"></i>{/if} {l s='Export documents' mod='dhldp'} </button>

					</div>

				</div>

				</td>

				<td>

					{if isset($last_label['id_dhldp_label'])}

						{if isset($last_label.is_return) && $last_label.is_return == 1}

							<span class="badge badge-warning">{l s='Return label' mod='dhldp'}</span><br>

						{/if}

						{l s='Shipment number' mod='dhldp'}: {$last_label.shipment_number|escape:'htmlall':'UTF-8'}<br>

						<a class="btn btn-primary btn-sm" href="{$last_label.label_url|escape:'html':'UTF-8'}" target="_blank"><i class="icon-print"></i> {l s='Print label' mod='dhldp'}</a>

						{if $last_label.return_label_url != ''}

							<a class="btn btn-primary btn-sm" href="{$last_label.return_label_url|escape:'html':'UTF-8'}" target="_blank"><i class="icon-print"></i> {l s='Print return label' mod='dhldp'}</a>

						{/if}

						{if $last_label.export_label_url != ''}

							<a class="btn btn-primary btn-sm" href="{$last_label.export_label_url|escape:'html':'UTF-8'}" target="_blank"><i class="icon-print"></i> {l s='Print export doc' mod='dhldp'}</a>

						{/if}

						{if $last_label.cod_label_url != ''}

							<a class="btn btn-primary btn-sm" href="{$last_label.cod_label_url|escape:'html':'UTF-8'}" target="_blank"><i class="icon-print"></i> {l s='Print COD doc' mod='dhldp'}</a>

						{/if}

						<input type="submit" class="btn btn-success btn-sm" id="submitDHLDPDhlLabelRequest" name="submitDHLDPDhlLabelRequest" value="{l s='Generate new label' mod='dhldp'}" /><br>

						{if isset($with_return) && $with_return == true}

							<input type="submit" class="btn btn-success btn-sm" name="submitDhlLabelWithReturnRequest" value="{l s='Generate label with return' mod='dhldp'}" /><br>

						{/if}

						<input type="hidden" name="shipment_number" value="{$last_label.shipment_number|escape:'htmlall':'UTF-8'}" />

						{if $last_label.product_code != 'rp'}

							{if $DHLDP_DHL_CREATE_MANIFEST_IN_ORDER}
								<input type="submit" class="btn btn-default btn-sm" name="doDHLDPDhlManifest" value="{l s='Do manifest' mod='dhldp'}">
							{/if}

							<input type="submit" class="btn btn-danger btn-sm" name="deleteDHLDPDhlLabel" value="{l s='Delete label' mod='dhldp'}">

						{/if}

						{if isset($enable_return) && $enable_return == true}

							<button type="submit" class="btn btn-warning btn-sm" name="submitDHLDPDhlLabelReturnRequest"><i class="icon-reply"></i> {l s='Return label' mod='dhldp'}</button><br>

						{/if}

						<a class="btn btn-default btn-sm" href="{$label.tracking_url|escape:'html':'UTF-8'}" target="_blank"><i class="icon-search"></i> {l s='Tracking' mod='dhldp'}</a>

						<br>

						<a class="btn btn-default dhldp-get-tracking-data btn-sm" href="#" data-tracking-number="{$last_label.shipment_number|escape:'html':'UTF-8'}"><i class="icon-truck"></i> {l s='Update tracking data' mod='dhldp'}</a>

						<div class="dhldp-last-tracking-data"></div>

					{else}

						<input type="submit" class="btn btn-success btn-sm" id="submitDHLDPDhlLabelRequest" name="submitDHLDPDhlLabelRequest" value="{l s='Generate label' mod='dhldp'}" />

						{if isset($with_return) && $with_return == true}

							<br><input type="submit" class="btn btn-success btn-sm" name="submitDHLDPDhlLabelWithReturnRequest" value="{l s='Generate label with return' mod='dhldp'}" />

						{/if}

					{/if}



				</td>

				</tr>

				</tbody>

				</table>

		</div>

		{include file="$self/views/templates/hook/additional-services.tpl"}

		{include file="$self/views/templates/hook/export-documents.tpl"}

		{include file="$self/views/templates/hook/update-address.tpl"}

		</form>

	</div>

</div>



</div>