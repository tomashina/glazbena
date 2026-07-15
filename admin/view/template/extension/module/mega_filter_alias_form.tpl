<?php require_once DIR_TEMPLATE . 'extension/module/mega_filter-fn.tpl'; ?>
<?php require DIR_TEMPLATE . 'extension/module/' . $_name . '-header.tpl'; ?>

<br />

<ul class="nav nav-tabs attributes">
	<li><a href="<?php echo $seo_url; ?>#tab-seo-settings"><i class="glyphicon glyphicon-wrench"></i> <?php echo $tab_seo_settings; ?></a></li>
	<li><a href="<?php echo $seo_url; ?>#tab-seo-sitemap"><i class="fa fa-sitemap"></i> <?php echo $tab_sitemap; ?></a></li>
	<li><a href="<?php echo $seo_url; ?>#tab-seo-other"><i class="glyphicon glyphicon-cog"></i> <?php echo $tab_other; ?></a></li>
	<li class="active"><a href="<?php echo $aliases_url; ?>"><i class="glyphicon glyphicon-random"></i> <?php echo $tab_aliases; ?></a></li>
</ul>
<br />
<div class="tab-content">
	<?php echo $text_store; ?>: <strong><?php echo $store_name; ?></strong>, <?php echo $text_language; ?>: <strong><?php echo $language_name; ?></strong>
	<form action="<?php echo $action_url; ?>" method="post" style="margin-top: 10px;">
		<table class="table">
			<thead>
				<tr>
					<th width="50%"><?php echo $text_url; ?></th>
					<th colspan="2"><?php echo $text_seo_url; ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td>
						<input type="text" class="form-control" name="url" value="<?php echo $val_url; ?>" />
						<small>e.g.: http://your-shop.com/desktops?mfp=price[0,100],manufacturers[8],3-clockspeed[100mhz]</small>
					</td>
					<td>
						<input type="text" class="form-control" name="alias" value="<?php echo $val_alias; ?>" />
						<small>e.g.: cheap-apple-desktops</small>
					</td>
					<td width="140" class="text-right">
						<a href="<?php echo $aliases_url; ?>" data-toggle="tooltip" title="<?php echo $button_back; ?>" class="btn btn-default"><i class="fa fa-reply"></i></a>
						<button type="submit" class="btn btn-success" id="insert-alias"><i class="glyphicon glyphicon-ok"></i> <?php echo $text_save; ?></button>
					</td>
				</tr>
				<tr>
					<td colspan="3">
						<table class="table table-tbody">
							<tbody>
								<tr>
									<td>
										<?php echo $entry_meta_title; ?><br >
										<input type="text" class="form-control" name="meta_title" value="<?php echo $val_meta_title; ?>" />
									</td>
									<td>
										<?php echo $entry_meta_description; ?><br >
										<textarea class="form-control" name="meta_description"><?php echo $val_meta_description; ?></textarea>
									</td>
									<td>
										<?php echo $entry_meta_keyword; ?><br >
										<textarea class="form-control" name="meta_keyword"><?php echo $val_meta_keyword; ?></textarea>
									</td>
								</tr>
								<tr>
									<td colspan="3">
										<?php echo $entry_h1; ?><br >
										<input type="text" class="form-control" name="h1" value="<?php echo $val_h1; ?>" />
									</td>
								</tr>
								<tr>
									<td colspan="3">
										<?php echo $entry_description; ?><br >
										<textarea class="form-control" name="description" rows="10"><?php echo $val_description; ?></textarea>
									</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
			</tbody>
		</table>
	</form>
</div>

<?php require DIR_TEMPLATE . 'extension/module/' . $_name . '-footer.tpl'; ?>