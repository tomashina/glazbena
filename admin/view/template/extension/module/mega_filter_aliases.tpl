<?php require_once DIR_TEMPLATE . 'extension/module/mega_filter-fn.tpl'; ?>
<?php require DIR_TEMPLATE . 'extension/module/' . $_name . '-header.tpl'; ?>

<br />

<ul class="nav nav-tabs attributes">
	<li><a href="<?php echo $seo_url; ?>#tab-seo-settings"><i class="glyphicon glyphicon-wrench"></i> <?php echo $tab_seo_settings; ?></a></li>
	<li><a href="<?php echo $seo_url; ?>#tab-seo-sitemap"><i class="fa fa-sitemap"></i> <?php echo $tab_sitemap; ?></a></li>
	<li><a href="<?php echo $seo_url; ?>#tab-seo-other"><i class="glyphicon glyphicon-cog"></i> <?php echo $tab_other; ?></a></li>
	<li><a href="<?php echo $seo_url; ?>#tab-translations"><i class="fa fa-language"></i> <?php echo $tab_translations; ?></a></li>
	<li class="active"><a href="<?php echo $aliases_url; ?>"><i class="glyphicon glyphicon-random"></i> <?php echo $tab_aliases; ?></a></li>
</ul>
<br />
<div class="tab-content">
	<table class="pull-left" id="mfp-search-form">
		<tr>
			<td>
				<div class="input-group">
					<input type="text" name="phrase" class="form-control" placeholder="<?php echo $text_phrase; ?>" value="<?php echo $phrase; ?>" />
					<div class="input-group-btn">
						<?php if( $phrase !== '' ) { ?>
							<button type="button" class="btn btn-danger" style="padding-right: 14px;"><i class="fa fa-remove"></i></button>
						<?php } ?>
						<button type="button" class="btn btn-primary"><i class="fa fa-filter"></i> <?php echo $text_search; ?></button>
					</div>
				</div>
			</td>
			<td>
				&nbsp;&nbsp;
				<a class="btn btn-success" href="<?php echo $insert_url; ?>"><i class="glyphicon glyphicon-plus-sign"></i> <?php echo $text_insert; ?></a>
			</td>
		</tr>
	</table>
	<table class="pull-right">
		<tr>
			<td>
				<div class="pull-left" style="padding: 5px;"><?php echo $text_languages; ?>:</div>
				<div class="btn-group">
					<?php foreach( $languages as $language ) { ?>
						<?php if( $language['language_id'] == $language_id ) { ?>
							<button type="button" class="btn btn-sm btn-primary active"><img src="<?php echo version_compare( VERSION, '2.2.0.0', '>=' ) ? 'language/' . $language['code'] . '/' . $language['code'] . '.png' : 'view/image/flags/' . $language['image']; ?>" alt="" /> <?php echo $language['name']; ?></button>
						<?php } else { ?>
							<a data-param="language_id" data-val="<?php echo $language['language_id']; ?>" href="<?php echo $aliases_url; ?>&language_id=<?php echo $language['language_id']; ?>&store_id=<?php echo $store_id; ?>" class="btn btn-sm btn-primary"><img src="<?php echo 'language/' . $language['code'] . '/' . $language['code'] . '.png'; ?>" /> <?php echo $language['name']; ?></a>
						<?php } ?>
					<?php } ?>
				</div>
			</td>
			<td>
				<div class="pull-left" style="padding: 5px;"><?php echo $text_stores; ?>:</div>
				<div class="btn-group">
					<?php foreach( $stores as $store ) { ?>
						<?php if( $store['store_id'] == $store_id ) { ?>
							<button type="button" class="btn btn-sm btn-primary active"><?php echo $store['name']; ?></button>
						<?php } else { ?>
							<a data-param="store_id" data-val="<?php echo $store['store_id']; ?>" href="<?php echo $aliases_url; ?>&store_id=<?php echo $store['store_id']; ?>&language_id=<?php echo $language_id; ?>" class="btn btn-sm btn-primary"><?php echo $store['name']; ?></a>
						<?php } ?>
					<?php } ?>
				</div>
			</td>
		</tr>
	</table>

	<div class="clearfix"></div>

	<?php if( $records ) { ?>
	<br />
		<table class="table table-striped">
			<thead>
				<tr>
					<th width="50%"><?php echo $text_url_params; ?></th>
					<th><?php echo $text_seo_url; ?></th>
					<th width="100"></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach( $records as $record ) { ?>
					<?php

						$url = $alias = HTTPS_CATALOG;

						if( $record['store_id'] && isset( $stores[$record['store_id']] ) ) {
							$url = $alias = empty( $stores[$record['store_id']]['ssl'] ) ? $stores[$record['store_id']]['url'] : $stores[$record['store_id']]['ssl'];
							$url = $alias = rtrim( $url, '/' ) . '/';
						}

						$url .= $record['path'] . ( $record['path'] ? '/' : '' ) . '?' . $url_parameter . '=' . $record['mfp'];
						$alias .= $record['path'] . '/' . $record['alias'];

					?>
					<tr>
						<td colspan="3">
							<table class="table table-tbody">
								<tbody>
									<tr>
										<td width="50%" colspan="3">
											<a href="<?php echo $url; ?>" target="_blank" data-name="url"><?php echo $url; ?></a>
										</td>
										<td colspan="3">
											<a href="<?php echo $alias; ?>" target="_blank" data-name="alias" data-val="<?php echo $record['alias']; ?>"><?php echo $alias; ?></a>
										</td>
										<td width="100" class="text-center">
											<a href="<?php echo $remove_url; ?>&id=<?php echo $record['mfilter_url_alias_id']; ?>" onclick="return confirm('<?php echo $text_are_you_sure; ?>');" class="btn btn-sm btn-danger"><i class="glyphicon glyphicon-remove"></i></a>
											<a href="<?php echo $insert_url; ?>&id=<?php echo $record['mfilter_url_alias_id']; ?>" class="btn btn-sm btn-primary"><i class="glyphicon glyphicon-edit"></i></a>
										</td>
									</tr>
									<?php if( ! empty( $record['meta_title'] ) || ! empty( $record['meta_description'] ) || ! empty( $record['meta_keyword'] ) ) { ?>
										<tr>
											<td colspan="2">
												<?php echo $entry_meta_title; ?><br >
												<input type="text" class="form-control" data-name="meta-title" readonly="readonly" value="<?php echo $record['meta_title']; ?>" />
											</td>
											<td colspan="2">
												<?php echo $entry_meta_description; ?><br >
												<textarea class="form-control" data-name="meta-description" readonly="readonly"><?php echo $record['meta_description']; ?></textarea>
											</td>
											<td colspan="2">
												<?php echo $entry_meta_keyword; ?><br >
												<textarea class="form-control" data-name="meta-keyword" readonly="readonly"><?php echo $record['meta_keyword']; ?></textarea>
											</td>
											<td></td>
										</tr>
									<?php } ?>
									<?php if( ! empty( $record['description'] ) || ! empty( $record['h1'] ) ) { ?>
										<tr>
											<td colspan="2">
												<?php echo $entry_h1; ?><br >
												<input type="text" class="form-control" data-name="h1" readonly="readonly" value="<?php echo $record['h1']; ?>" />
											</td>
											<td colspan="4">
												<?php echo $entry_description; ?><br >
												<textarea class="form-control" data-name="description" readonly="readonly"><?php echo $record['description']; ?></textarea>
											</td>
											<td></td>
										</tr>
									<?php } ?>
								</tbody>
							</table>
						</td>
					</tr>
				<?php } ?>
			</tbody>
		</table>

		<?php echo $pagination; ?>
	<?php } else { ?>
		<br />
		<div class="text-center"><?php echo $text_list_is_empty; ?></div>
	<?php } ?>
</div>

<script type="text/javascript">
	$('#mfp-search-form').each(function(){
		var _this = $(this),
			confirm = _this.find('button.btn-primary'),
			clear = _this.find('button.btn-danger'),
			txt = _this.find('input[type=text]'),
			phrase = _this.find('input[name=phrase]'),
			url = '<?php echo $search_url; ?>'.replace(/&amp;/g, '&');
		
		url += '&store_id=<?php echo $store_id; ?>';
		url += '&language_id=<?php echo $language_id; ?>';
		
		confirm.click(function(){
			url += '&phrase=' + encodeURIComponent( phrase.val() );
			
			document.location = url;
		});
		
		clear.click(function(){
			document.location = url;
		});
		
		txt.keydown(function(e){
			if( e.keyCode == 13 ) {
				btn.trigger('click');
			}
		});
	});
</script>

<?php require DIR_TEMPLATE . 'extension/module/' . $_name . '-footer.tpl'; ?>