<?php require DIR_TEMPLATE . 'extension/module/' . $_name . '-header.tpl'; ?>

<form action="<?php echo $action_form; ?>" method="post">
	<br /><?php echo $text_select_data_to_export; ?><br />

	<table class="table">
		<tr>
			<td>
				<?php echo $data_form; ?>
			</td>
		</tr>
		<tr>
			<td>
				<button type="submit" class="btn btn-primary"><?php echo $text_export; ?></button>
			</td>
		</tr>
	</table>
</form>
			
<?php require DIR_TEMPLATE . 'extension/module/' . $_name . '-footer.tpl'; ?>