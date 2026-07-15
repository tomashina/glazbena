<?php require_once DIR_TEMPLATE . 'extension/module/mega_filter-fn.tpl'; ?>
<?php require DIR_TEMPLATE . 'extension/module/' . $_name . '-header.tpl'; ?>

<br />

<?php if( empty( $seo_base_settings ) ) { ?>
<ul class="nav nav-tabs attributes">
	<li class="active"><a data-toggle="tab" href="#tab-seo-settings"><i class="glyphicon glyphicon-wrench"></i> <?php echo $tab_seo_settings; ?></a></li>
	<li><a data-toggle="tab" href="#tab-seo-sitemap"><i class="fa fa-sitemap"></i> <?php echo $tab_sitemap; ?></a></li>
	<li><a data-toggle="tab" href="#tab-seo-other"><i class="glyphicon glyphicon-cog"></i> <?php echo $tab_other; ?></a></li>
	<li><a data-toggle="tab" href="#tab-translations"><i class="fa fa-language"></i> <?php echo $tab_translations; ?></a></li>
	<li><a href="<?php echo $aliases_url; ?>"><i class="glyphicon glyphicon-random"></i> <?php echo $tab_aliases; ?></a></li>
</ul>
<br />
<div class="tab-content">
	<div class="tab-pane active" id="tab-seo-settings">
<?php } ?>
		<table class="table table-tbody">
			<?php if( empty( $seo_base_settings ) ) { ?>
				<tr>
					<td width="200"><?php echo $text_seo_enabled; ?>:</td>
					<td>
						<?php echo mf_render_btn_group( $text_yes, $text_no, $_name . '_seo[enabled]', ! empty( $seo['enabled'] ) ); ?>
					</td>
				</tr>
				<tr id="seo-aliases-enabled">
					<td width="200"><?php echo $text_seo_aliases_enabled; ?>:</td>
					<td>
						<?php echo mf_render_btn_group( $text_yes, $text_no, $_name . '_seo[aliases_enabled]', ! empty( $seo['aliases_enabled'] ) ); ?>
					</td>
				</tr>
				<tr>
					<td colspan="2" style="padding: 20px 0 10px; border-top: none;">
						<strong><?php echo $text_seo_enabled_help; ?>:</strong>
					</td>
				</tr>
			<?php } ?>
			<?php if( ! empty( $mfp_plus_version ) ) { ?>
				<tr>
					<td width="200"><?php echo $text_convert_non_latin; ?>:</td>
					<td>
						<?php echo mf_render_btn_group( $text_yes, $text_no, $_name . '_seo[convert_non_to_latin]', ! isset( $seo['convert_non_to_latin'] ) || $seo['convert_non_to_latin'] ); ?>
						<br />
						<div style="margin: 7px 0; padding: 3px 5px; background: #ffffdf; border: 1px solid #e9eeb1; display: inline-block">
							<i class="fa fa-info-circle"></i> <?php echo $text_convert_non_latin_guide; ?><br />
							<?php if( empty( $seo_base_settings ) ) { ?>
								<i class="fa fa-exclamation-circle"></i> <?php echo $text_requires_rebuild_index; ?>
							<?php } ?>
						</div>
					</td>
				</tr>
				<tr>
					<td width="200"><?php echo $text_convert_to_lowercase; ?>:</td>
					<td>
						<?php echo mf_render_btn_group( $text_yes, $text_no, $_name . '_seo[convert_to_lowercase]', ! isset( $seo['convert_to_lowercase'] ) || $seo['convert_to_lowercase'] ); ?>
						<?php if( empty( $seo_base_settings ) ) { ?>
							<br />
							<div style="margin: 7px 0; padding: 3px 5px; background: #ffffdf; border: 1px solid #e9eeb1; display: inline-block">
								<i class="fa fa-exclamation-circle"></i> <?php echo $text_requires_rebuild_index; ?>
							</div>
						<?php } ?>
					</td>
				</tr>
				<?php if( ! empty( $seo_base_settings ) ) { ?>
					<tr>
						<td><?php echo $entry_disable_tags_indexing; ?></td>
						<td>
							<?php echo mf_render_btn_group( $text_yes, $text_no, $_name . '_settings[disable_tags_indexing]', ! empty( $settings['disable_tags_indexing'] ) ); ?>
							<br />
							<div style="margin: 7px 0; padding: 3px 5px; background: #ffffdf; border: 1px solid #e9eeb1; display: inline-block;">
								<i class="fa fa-info-circle"></i> <?php echo $entry_disable_tags_indexing_help; ?>
							</div>
						</td>
					</tr>
				<?php } ?>
			<?php } ?>
			<?php if( empty( $seo_base_settings ) ) { ?>
				<tr>
					<td width="200"><?php echo $text_filter_seo_separator; ?>:</td>
					<td>
						<?php $key = 0; foreach( $languages as $language ) { ?>
							<input type="text" name="<?php echo $_name; ?>_seo[separator][<?php echo $language['language_id']; ?>]" value="<?php echo empty( $seo['separator'][$language['language_id']] ) ? 'mfp' : $seo['separator'][$language['language_id']]; ?>" />
							<img src="<?php echo version_compare( VERSION, '2.2.0.0', '>=' ) ? 'language/' . $language['code'] . '/' . $language['code'] . '.png' : 'view/image/flags/' . $language['image']; ?>" alt="<?php echo $language['name']; ?>" title="<?php echo $language['name']; ?>" />

							<?php if( ! $key ) { ?>
								<?php echo $text_eg; ?>: <code>http://your-store.com/desktop/<b style="background: #000; color: #fff; display: inline-block; padding: 0 3px;"><?php echo empty( $seo['separator'][$language['language_id']] ) ? 'mfp' : $seo['separator'][$language['language_id']]; ?></b>/manufacturer,apple</code>
							<?php } ?>
							<br />
						<?php $key++; } ?>
						<div style="margin: 7px 0; padding: 3px 5px; background: #ffffdf; border: 1px solid #e9eeb1; display: inline-block">
							<i class="fa fa-info-circle"></i> <?php echo $text_filter_seo_separator_guide; ?>
						</div>
					</td>
				</tr>
				<tr>
					<td><?php echo $entry_add_slash_at_the_end_of_seo_urls; ?></td>
					<td>
						<?php echo mf_render_btn_group( $text_yes, $text_no, $_name . '_seo[add_slash_at_the_end]', ! empty( $seo['add_slash_at_the_end'] ) ); ?>
						<br />
						<div style="margin: 7px 0; padding: 3px 5px; background: #ffffdf; border: 1px solid #e9eeb1; display: inline-block">
							<i class="fa fa-info-circle"></i> <?php echo $text_add_slash_at_the_end_of_seo_urls_help; ?>
						</div>
					</td>
				</tr>
				<tr>
					<td><?php echo $entry_auto_redirect_to_seo_aliases; ?></td>
					<td>
						<?php echo mf_render_btn_group( $text_yes, $text_no, $_name . '_seo[auto_redirect_to_seo_aliases]', ! empty( $seo['auto_redirect_to_seo_aliases'] ) ); ?>
						<br />
						<div style="margin: 7px 0; padding: 3px 5px; background: #ffffdf; border: 1px solid #e9eeb1; display: inline-block">
							<i class="fa fa-info-circle"></i> <?php echo $text_auto_redirect_to_seo_aliases_help; ?>
						</div>
					</td>
				</tr>
			<?php } else { ?>
				<tr>
					<td colspan="2" class="text-center">
						<button type="submit" class="btn btn-primary"><?php echo $text_next; ?> <i class="fa fa-arrow-right"></i></button>
					</td>
				</tr>
			<?php } ?>
		</table>
<?php if( empty( $seo_base_settings ) ) { ?>
	</div>
	<div class="tab-pane" id="tab-seo-sitemap">
		<table class="table table-tbody">
			<tr>
				<td width="200"><?php echo $text_sitemaps; ?>:</td>
				<td>
					<table>
						<tr>
							<td width="55">
								<?php echo mf_render_btn_group( $text_yes, $text_no, $_name . '_seo[sitemap][enabled]', ! empty( $seo['sitemap']['enabled'] ) ); ?>
							</td>
							<td>
								<?php echo $text_show_on_sitemap; ?>
							</td>
						</tr>
						<tr>
							<td colspan="2" style="padding-bottom: 10px;">
								<div style="margin: 7px 0; padding: 3px 5px; background: #ffffdf; border: 1px solid #e9eeb1; display: inline-block">
									<i class="fa fa-info-circle"></i> <?php echo $text_sitemap_guide; ?>
								</div>
							</td>
						</tr>
						<tr>
							<td style="padding-bottom: 10px;">
								<?php echo mf_render_btn_group( $text_yes, $text_no, $_name . '_seo[google_sitemap][enabled]', ! empty( $seo['google_sitemap']['enabled'] ) ); ?>
							</td>
							<td style="padding-bottom: 10px;">
								<?php echo $text_add_to_google_sitemap; ?>
							</td>
						</tr>
						<tr>
							<td colspan="2" style="padding-bottom: 10px;">
								<?php echo $text_sitemap_changefreq; ?>: 
								<?php $changefreq = array( 'always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never' ); ?>
								<select name="<?php echo $_name . '_seo[google_sitemap][changefreq]'; ?>">
									<?php foreach( $changefreq as $val ) { ?>
										<option value="<?php echo $val; ?>" <?php echo ( empty( $seo['google_sitemap']['changefreq'] ) && $val == 'weekly' ) || ! empty( $seo['google_sitemap']['changefreq'] ) && $seo['google_sitemap']['changefreq'] == $val ? ' selected="selected"' : ''; ?>><?php echo $val; ?></option>
									<?php } ?>
								</select>
							</td>
						</tr>
						<tr>
							<td colspan="2">
								<?php echo $text_sitemap_priority; ?>: 
								<select name="<?php echo $_name . '_seo[google_sitemap][priority]'; ?>">
									<?php for( $i = 0.1; $i <= 1; $i+=0.1 ) { ?>
										<option value="<?php echo $i; ?>" <?php echo ( empty( $seo['google_sitemap']['priority'] ) && $i == 0.7 ) || ! empty( $seo['google_sitemap']['priority'] ) && (string) $seo['google_sitemap']['priority'] == (string) $i ? ' selected="selected"' : ''; ?>><?php echo $i; ?></option>
									<?php } ?>
								</select>
							</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
	</div>
	<div class="tab-pane" id="tab-seo-other">
		<table class="table table-tbody">
				<tr>
					<td width="230"><?php echo $text_seo_values_are_links; ?>:</td>
					<td>
						<?php echo mf_render_btn_group( $text_yes, $text_no, $_name . '_seo[values_are_links]', ! empty( $seo['values_are_links'] ) ); ?>
					</td>
				</tr>
				<tr id="values-links-are-clickable">
				<td width="200"><?php echo $text_seo_values_links_are_clickable; ?>:</td>
					<td>
						<?php echo mf_render_btn_group( $text_yes, $text_no, $_name . '_seo[values_links_are_clickable]', ! empty( $seo['values_links_are_clickable'] ) ); ?>
						<br />
						<div style="margin: 7px 0; padding: 3px 5px; background: #ffffdf; border: 1px solid #e9eeb1; display: inline-block">
							<i class="fa fa-info-circle"></i> <?php echo $text_seo_values_links_are_clickable_help; ?>
						</div>
					</td>
				</tr>
			<tr>
				<td width="200"><?php echo $text_filter_url_parameter; ?>:</td>
				<td>
					<input type="text" name="<?php echo $_name; ?>_seo[url_parameter]" value="<?php echo empty( $seo['url_parameter'] ) ? 'mfp' : $seo['url_parameter']; ?>" /> <?php echo $text_eg; ?>: <code>http://your-store.com/index.php?route=product/category&path=1&<b style="background: #000; color: #fff; display: inline-block; padding: 0 3px;"><?php echo empty( $seo['url_parameter'] ) ? 'mfp' : $seo['url_parameter']; ?></b>=manufacturer[1]</code>
					<br />
					<div style="margin: 7px 0; padding: 3px 5px; background: #ffffdf; border: 1px solid #e9eeb1; display: inline-block">
						<i class="fa fa-info-circle"></i> <?php echo $text_filter_url_parameter_guide; ?>
					</div>
				</td>
			</tr>
			<tr>
				<td width="200"><?php echo $entry_set_meta_robots; ?></td>
				<td>
					<?php echo mf_render_btn_group( $text_yes, $text_no, $_name . '_seo[meta_robots]', ! empty( $seo['meta_robots'] ) ); ?>
					<br />
					<div class="hide" style="padding-top: 5px" id="meta-robots-value">
						<div style="margin: 7px 0; padding: 3px 5px; background: #ffffdf; border: 1px solid #e9eeb1; display: inline-block">
							<i class="fa fa-info-circle"></i> <?php echo str_replace( ':value', empty( $seo['meta_robots_value'] ) ? 'noindex,follow' : $seo['meta_robots_value'], $text_set_meta_robots_help ); ?>
						</div>
						<br />
						<code>&lt;meta name="robots" content="</code><input type="text" name="<?php echo $_name; ?>_seo[meta_robots_value]" value="<?php echo empty( $seo['meta_robots_value'] ) ? 'noindex,follow' : $seo['meta_robots_value']; ?>" /><code>"/&gt</code>
					</div>
				</td>
			</tr>
			<tr>
				<td width="200"><?php echo $entry_redirect_to_correct_lang_by_seo_url; ?></td>
				<td>
					<?php echo mf_render_btn_group( $text_yes, $text_no, $_name . '_seo[redirect_to_correct_lang_by_seo_url]', ! empty( $seo['redirect_to_correct_lang_by_seo_url'] ) ); ?>
					<br />
					<div style="margin: 7px 0; padding: 3px 5px; background: #ffffdf; border: 1px solid #e9eeb1; display: inline-block">
						<i class="fa fa-info-circle"></i> <?php echo $text_redirect_to_correct_lang_by_seo_url_help; ?>
					</div>
				</td>
			</tr>
			<tr>
				<td colspan="2" style="padding: 20px 0 10px; border-top: none;">
					<strong><?php echo $text_seo_enabled_help; ?>:</strong>
				</td>
			</tr>
			<tr>
				<td><?php echo $entry_use_post_ajax_requests; ?></td>
				<td>
					<?php echo mf_render_btn_group( $text_yes, $text_no, $_name . '_seo[use_post_ajax_requests]', ! empty( $seo['use_post_ajax_requests'] ) ); ?>
					<br />
					<div style="margin: 7px 0; padding: 3px 5px; background: #ffffdf; border: 1px solid #e9eeb1; display: inline-block">
						<i class="fa fa-info-circle"></i> <?php echo $text_use_post_ajax_requests_help; ?>
					</div>
				</td>
			</tr>
			<tr id="headers-ajax-response">
				<td><?php echo $entry_add_noindex_header; ?></td>
				<td>
					<?php echo mf_render_btn_group( $text_yes, $text_no, $_name . '_seo[header_noindex]', ! empty( $seo['header_noindex'] ) ); ?>
					<br />
					<div style="margin: 7px 0; padding: 3px 5px; background: #ffffdf; border: 1px solid #e9eeb1; display: inline-block">
						<i class="fa fa-info-circle"></i> <?php echo $text_add_noindex_header_help; ?>
					</div>
				</td>
			</tr>
			<tr id="return-full-ajax-response">
				<td><?php echo $entry_return_full_ajax_response; ?></td>
				<td>
					<?php echo mf_render_btn_group( $text_yes, $text_no, $_name . '_seo[return_full_ajax_response]', ! empty( $seo['return_full_ajax_response'] ) ); ?>
					<br />
					<div style="margin: 7px 0; padding: 3px 5px; background: #ffffdf; border: 1px solid #e9eeb1; display: inline-block">
						<i class="fa fa-info-circle"></i> <?php echo $text_return_full_ajax_response_help; ?>
					</div>
				</td>
			</tr>
		</table>
	</div>
	<div class="tab-pane" id="tab-translations">
		<div style="margin: 0 auto 20px; padding: 3px 5px; background: #ffffdf; border: 1px solid #e9eeb1; display: inline-block">
			<i class="fa fa-info-circle"></i> <?php echo $text_trans_seo_tab; ?>
		</div>
		
		<?php
			$names_base_attrib = array(
				'price', 'manufacturers', 'model', 'sku', 'upc', 'ean', 'jan', 'isbn', 'mpn', 'location', 'length', 'width', 'height', 'weight', 'tags', 'discounts', 'stock_status',
			);
		?>
		<table class="table">
			<thead>
				<tr>
					<th colspan="2"><?php echo $tab_base_attributes; ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach( $names_base_attrib as $attrib ) { ?>
					<tr>
						<td width="200"><?php echo ${'attrib_'.$attrib}; ?>:</td>
						<td>
							<?php foreach( $languages as $language ) { ?>
								<div class="input-group" style="margin: 1px 0;">
									<input class="form-control" type="text" name="<?php echo $_name; ?>_seo[trans][base_attribs][<?php echo $attrib; ?>][<?php echo $language['language_id']; ?>]" placeholder="<?php echo $language['name']; ?>" value="<?php echo empty( $seo['trans']['base_attribs'][$attrib][$language['language_id']] ) ? '' : $seo['trans']['base_attribs'][$attrib][$language['language_id']]; ?>" />
									<div class="input-group-addon">
										<img src="<?php echo 'language/' . $language['code'] . '/' . $language['code'] . '.png'; ?>" alt="<?php echo $language['name']; ?>" title="<?php echo $language['name']; ?>" />
									</div>
								</div>
							<?php } ?>
						</td>
					</tr>
				<?php } ?>
			</tbody>
		</table>
	</div>
</div>

<script type="text/javascript">
	(function(){
		var tab = document.location.href.split('#')[1];
		
		if( typeof tab != 'undefined' ) {
			$('a[href="#'+tab+'"]').trigger('click');
		}
	})();
	
	$('[name*="values_are_links"]').change(function(){
		$('#values-links-are-clickable')[$(this).val() == '1'?'removeClass':'addClass']('hide');
	});
	
	$('[name*="values_are_links"]:checked').trigger('change');
	
	$('[name*="_seo[enabled]"]').change(function(){
		$('#seo-aliases-enabled')[$(this).val() == '1'?'addClass':'removeClass']('hide');
	});
	
	$('[name*="_seo[enabled]"]:checked').trigger('change');
	
	$('[name*="_seo[meta_robots]"]').change(function(){
		$('#meta-robots-value')[$(this).val() != '1'?'addClass':'removeClass']('hide');
	});
	
	$('[name*="_seo[meta_robots]"]:checked').trigger('change');
	
	$('[name*="_seo[use_post_ajax_requests]"]').change(function(){
		$('#headers-ajax-response')[$(this).val() == '1'?'addClass':'removeClass']('hide');
		$('#return-full-ajax-response')[$(this).val() != '1'?'addClass':'removeClass']('hide');
	});
	
	$('[name*="_seo[use_post_ajax_requests]"]:checked').trigger('change');
</script>
<?php } ?>

<?php require DIR_TEMPLATE . 'extension/module/' . $_name . '-footer.tpl'; ?>