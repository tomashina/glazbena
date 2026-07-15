<?php require DIR_TEMPLATE . 'extension/module/' . $_name . '-header.tpl'; ?>

<?php if( isset( $data_form ) ) { ?>
	<br />
	
	<div class="alert alert-danger"><?php echo $text_currently_settings_will_be_replaced; ?></div>
	
	<form action="<?php echo $action_form; ?>" method="post" enctype="multipart/form-data">
		<table class="table">
			<thead>
				<tr>
					<th>
						<?php echo $text_select_data_to_import; ?>
					</th>
					<th>
						<?php echo $text_select_the_same_data; ?>
					</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td>
						<?php echo $data_form; ?>
					</td>
					<td>
						<strong><?php echo $text_stores; ?></strong>
						<div style="padding-left: 10px; line-height: 28px;">
							<?php foreach( $data_oc['stores'] as $row ) { ?>
								<?php echo $row['name']; ?> &rightarrow; <select name="settings[stores][<?php echo $row['store_id']; ?>]">
									<option value=""><?php echo $text_none_dont_import_this_option; ?></option>
									<?php foreach( $stores as $store ) { ?>
										<option 
											value="<?php echo $store['store_id']; ?>"
											<?php echo ( isset( $post['settings']['stores'][$row['store_id']] ) && $post['settings']['stores'][$row['store_id']] == $store['store_id'] ) || ( ! isset( $post ) && ( $row['url'] == $store['url'] || $row['store_id'] == $store['store_id'] ) ) ? ' selected="selected"' : ''; ?>
										><?php echo $store['name']; ?> (<?php echo $store['url']; ?>)</option>
									<?php } ?>
								</select><br />
							<?php } ?>
						</div>
							
						<?php if( ! empty( $data_oc['languages'] ) ) { ?>
							<hr />
							<strong><?php echo $text_languages; ?></strong>
							<div style="padding-left: 10px; line-height: 28px;">
								<?php foreach( $data_oc['languages'] as $row ) { ?>
									<?php echo $row['name']; ?> &rightarrow; <select name="settings[languages][<?php echo $row['language_id']; ?>]">
										<option value=""><?php echo $text_none_dont_import_this_option; ?></option>
										<?php foreach( $languages as $language ) { ?>
											<option 
												value="<?php echo $language['language_id']; ?>"
												<?php echo ( isset( $post['settings']['languages'][$row['language_id']] ) && $post['settings']['languages'][$row['language_id']] == $language['language_id'] ) || ( ! isset( $post ) && ( $row['language_id'] == $language['language_id'] || $row['name'] == $language['name'] || $row['code'] == $language['code'] ) ) ? ' selected="selected"' : ''; ?>
											><?php echo $language['name']; ?></option>
										<?php } ?>
									</select><br />
								<?php } ?>
							</div>
						<?php } ?>
							
						<?php if( ! empty( $data_oc['layouts'] ) ) { ?>
							<hr />
							<strong><?php echo $text_layouts; ?></strong>
							<div style="padding-left: 10px; line-height: 28px;">
								<?php foreach( $data_oc['layouts'] as $row ) { ?>
									<?php echo $row['name']; ?> &rightarrow; <select name="settings[layouts][<?php echo $row['layout_id']; ?>]">
										<option value=""><?php echo $text_none_dont_import_this_option; ?></option>
										<?php foreach( $layouts as $layout ) { ?>
											<option 
												value="<?php echo $layout['layout_id']; ?>"
												<?php echo ( isset( $post['settings']['layouts'][$row['layout_id']] ) && $post['settings']['layouts'][$row['layout_id']] == $layout['layout_id'] ) || ( ! isset( $post ) && ( $row['layout_id'] == $layout['layout_id'] || $row['name'] == $layout['name'] ) ) ? ' selected="selected"' : ''; ?>
											><?php echo $layout['name']; ?></option>
										<?php } ?>
									</select><br />
								<?php } ?>
							</div>
						<?php } ?>
							
						<?php if( ! empty( $data_oc['customer_groups'] ) ) { ?>
							<hr />
							<strong><?php echo $text_customer_groups; ?></strong>
							<div style="padding-left: 10px; line-height: 28px;">
								<?php foreach( $data_oc['customer_groups'] as $row ) { ?>
									<?php echo $row['name']; ?> &rightarrow; <select name="settings[customer_groups][<?php echo $row['customer_group_id']; ?>]">
										<option value=""><?php echo $text_none_dont_import_this_option; ?></option>
										<?php foreach( $customer_groups as $customer_group ) { ?>
											<option 
												value="<?php echo $customer_group['customer_group_id']; ?>"
												<?php echo ( isset( $post['settings']['customer_groups'][$row['customer_group_id']] ) && $post['settings']['customer_groups'][$row['customer_group_id']] == $customer_group['customer_group_id'] ) || ( ! isset( $post ) && ( $row['customer_group_id'] == $customer_group['customer_group_id'] || $row['name'] == $customer_group['name'] ) ) ? ' selected="selected"' : ''; ?>
											><?php echo $customer_group['name']; ?></option>
										<?php } ?>
									</select><br />
								<?php } ?>
							</div>
						<?php } ?>
							
						<?php if( ! empty( $data_oc['categories'] ) ) { ?>
							<hr />
							<strong><?php echo $text_categories; ?></strong>
							<div style="padding-left: 10px; line-height: 28px;">
								<?php foreach( $data_oc['categories'] as $row ) { ?>
									<?php echo $row['name']; ?> &rightarrow; <select name="settings[categories][<?php echo $row['category_id']; ?>]">
										<option value=""><?php echo $text_none_dont_import_this_option; ?></option>
										<?php foreach( $categories as $category ) { ?>
											<option 
												value="<?php echo $category['category_id']; ?>"
												<?php echo ( isset( $post['settings']['categories'][$row['category_id']] ) && $post['settings']['categories'][$row['category_id']] == $category['category_id'] ) || ( ! isset( $post ) && ( $row['category_id'] == $category['category_id'] || $row['name'] == $category['name'] ) ) ? ' selected="selected"' : ''; ?>
											><?php echo $category['name']; ?></option>
										<?php } ?>
									</select><br />
								<?php } ?>
							</div>
						<?php } ?>
							
						<?php if( ! empty( $data_oc['attribute_groups'] ) ) { ?>
							<hr />
							<strong><?php echo $text_attribute_groups; ?></strong>
							<div style="padding-left: 10px; line-height: 28px;">
								<?php foreach( $data_oc['attribute_groups'] as $row ) { ?>
									<?php echo $row['name']; ?> &rightarrow; <select name="settings[attribute_groups][<?php echo $row['attribute_group_id']; ?>]">
										<option value=""><?php echo $text_none_dont_import_this_option; ?></option>
										<?php foreach( $attribute_groups as $attribute_group ) { if( $attribute_group['language_id'] != $config_language_id ) continue; ?>
											<option 
												value="<?php echo $attribute_group['attribute_group_id']; ?>"
												<?php echo ( isset( $post['settings']['attribute_groups'][$row['attribute_group_id']] ) && $post['settings']['attribute_groups'][$row['attribute_group_id']] == $attribute_group['attribute_group_id'] ) || ( ! isset( $post ) && ( $row['attribute_group_id'] == $attribute_group['attribute_group_id'] || $row['name'] == $attribute_group['name'] ) ) ? ' selected="selected"' : ''; ?>
											><?php echo $attribute_group['name']; ?></option>
										<?php } ?>
									</select><br />
								<?php } ?>
							</div>
						<?php } ?>
							
						<?php if( ! empty( $data_oc['attributes'] ) ) { ?>
							<hr />
							<strong><?php echo $text_attributes; ?></strong>
							<div style="padding-left: 10px; line-height: 28px;">
								<?php foreach( $data_oc['attributes'] as $row ) { if( $row['language_id'] != $config_language_id ) continue; ?>
									<?php echo $row['name']; ?> &rightarrow; <select name="settings[attributes][<?php echo $row['attribute_id']; ?>]">
										<option value=""><?php echo $text_none_dont_import_this_option; ?></option>
										<?php foreach( $attributes as $attribute ) { ?>
											<option 
												value="<?php echo $attribute['attribute_id']; ?>"
												<?php echo ( isset( $post['settings']['attributes'][$row['attribute_id']] ) && $post['settings']['attributes'][$row['attribute_id']] == $attribute['attribute_id'] ) || ( ! isset( $post ) && ( $row['attribute_id'] == $attribute['attribute_id'] || $row['name'] == $attribute['name'] ) ) ? ' selected="selected"' : ''; ?>
											><?php echo $attribute['name']; ?></option>
										<?php } ?>
									</select><br />
								<?php } ?>
							</div>
						<?php } ?>
							
						<?php if( ! empty( $data_oc['options'] ) ) { ?>
							<hr />
							<strong><?php echo $text_options; ?></strong>
							<div style="padding-left: 10px; line-height: 28px;">
								<?php foreach( $data_oc['options'] as $row ) { if( $row['language_id'] != $config_language_id ) continue; ?>
									<?php echo $row['name']; ?> &rightarrow; <select name="settings[options][<?php echo $row['option_id']; ?>]">
										<option value=""><?php echo $text_none_dont_import_this_option; ?></option>
										<?php foreach( $options as $option ) { ?>
											<option 
												value="<?php echo $option['option_id']; ?>"
												<?php echo ( isset( $post['settings']['options'][$row['option_id']] ) && $post['settings']['options'][$row['option_id']] == $option['option_id'] ) || ( ! isset( $post ) && ( $row['option_id'] == $option['option_id'] || $row['name'] == $option['name'] ) ) ? ' selected="selected"' : ''; ?>
											><?php echo $option['name']; ?></option>
										<?php } ?>
									</select><br />
								<?php } ?>
							</div>
						<?php } ?>
							
						<?php if( ! empty( $data_oc['filters'] ) ) { ?>
							<hr />
							<strong><?php echo $text_filters; ?></strong>
							<div style="padding-left: 10px; line-height: 28px;">
								<?php foreach( $data_oc['filters'] as $row ) { ?>
									<?php echo $row['name']; ?> &rightarrow; <select name="settings[filters][<?php echo $row['filter_id']; ?>]">
										<option value=""><?php echo $text_none_dont_import_this_option; ?></option>
										<?php foreach( $filters as $filter ) { ?>
											<option 
												value="<?php echo $filter['filter_id']; ?>"
												<?php echo ( isset( $post['settings']['filters'][$row['filter_id']] ) && $post['settings']['filters'][$row['filter_id']] == $filter['filter_id'] ) || ( ! isset( $post ) && ( $row['filter_id'] == $filter['filter_id'] || $row['name'] == $filter['name'] ) ) ? ' selected="selected"' : ''; ?>
											><?php echo $filter['name']; ?></option>
										<?php } ?>
									</select><br />
								<?php } ?>
							</div>
						<?php } ?>
					</td>
				</tr>
			</tbody>
			<tfoot>
				<tr>
					<td colspan="2">
						<a class="btn btn-danger" href="<?php echo $action_delete_imported_file; ?>"><?php echo $text_delete_imported_file; ?></a>
						<button type="submit" class="btn btn-primary"><?php echo $text_import; ?></button>
					</td>
				</tr>
			</tfoot>
		</table>
	</form>	
<?php } else { ?>
	<br /><?php echo $text_select_file_to_import; ?><br /><br />
	
	<form action="<?php echo $action_form; ?>" method="post" enctype="multipart/form-data">
		<table>
			<tr>
				<td>
					<input type="file" name="file" />
				</td>
				<td>
					<button type="submit" class="btn btn-primary"><?php echo $text_import; ?></button>
				</td>
			</tr>
		</table>
	</form>
<?php } ?>
			
<?php require DIR_TEMPLATE . 'extension/module/' . $_name . '-footer.tpl'; ?>