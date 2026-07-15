<?php if ( ! empty( $_error_warning ) ) { ?>
	<br />
	<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $_error_warning; ?>
		<button type="button" class="close" data-dismiss="alert">&times;</button>
	</div>
<?php } ?>
<?php if ( ! empty( $_success ) ) { ?>
	<br />
	<div class="alert alert-success"><i class="fa fa-exclamation-circle"></i> <?php echo $_success; ?>
		<button type="button" class="close" data-dismiss="alert">&times;</button>
	</div>
<?php } ?>

<div class="clearfix"></div>
		
<br /><br />
<table class="table">
	<tbody>
		<tr>
			<td width="100">
				<?php echo $text_name; ?>:
			</td>
			<td>
				<input type="text" class="form-control" id="val-name" value="<?php echo $val_name; ?>" />
			</td>
		</tr>
		<tr>
			<td width="100">
				<?php echo $text_unique_id; ?>:
			</td>
			<td>
				<input type="text" readonly="readonly" class="form-control" id="val-unique-id" value="<?php echo $val_unique_id; ?>" />
				<small><?php echo $text_unique_id_help; ?></small>
			</td>
		</tr>
		<tr>
			<td>
				<?php echo $text_code; ?>:
			</td>
			<td>
				<textarea class="form-control" id="val-code" rows="20"><?php echo $val_code; ?></textarea>
			</td>
		</tr>
		<tr>
			<td colspan="2" class="text-right">
				<button type="button" class="btn btn-sm btn-success" id="insert-theme"><i class="glyphicon glyphicon-plus-sign"></i> <?php echo $text_insert; ?></button>
			</td>
		</tr>
	</tbody>
</table>

<?php if( $themes ) { ?>
	<table class="table table-striped">
		<thead>
			<tr>
				<th><?php echo $text_name; ?></th>
				<th width="200"><?php echo $text_unique_id; ?></th>
				<th width="100"></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach( $themes as $unique_id => $theme ) { ?>
				<tr>
					<td>
						<?php echo $theme['name']; ?>
						<input type="hidden" data-val="name" value="<?php echo $theme['name']; ?>" />
						<textarea class="hide" data-val="code"><?php echo $theme['code']; ?></textarea>
						<input type="hidden" data-val="unique-id" value="<?php echo $unique_id; ?>" />
					</td>
					<td>
						<?php echo $unique_id; ?>
					</td>
					<td width="100" class="text-center">
						<a href="#" data-id-to-remove="<?php echo $unique_id; ?>" class="btn btn-sm btn-danger"><i class="glyphicon glyphicon-remove"></i></a>
						<a href="#" data-id-to-edit="<?php echo $unique_id; ?>" class="btn btn-sm btn-primary"><i class="glyphicon glyphicon-edit"></i></a>
					</td>
				</tr>
			<?php } ?>
		</tbody>
	</table>
<?php } ?>

<script type="text/javascript">
	(function(){		
		function ajax( action, paramsPost, paramsGet ) {
			if( typeof params == 'undefined' ) {
				params = {};
			}
			
			$('#tab-themes').html('<center><?php echo $text_loading; ?>...</center>');
			
			$.post( '<?php echo $themes_url; ?>'.replace( /&amp;/g, '&' ) + 
					'&action=' + action + 
					( paramsGet ? '&' + paramsGet : ''), 
				paramsPost, 
				function( response ){
					$('#tab-themes').html( response );
				}
			);
		}
		
		$('#insert-theme').click(function(){
			var name = $('#val-name').val(),
				code = $('#val-code').val(),
				unique_id = $('#val-unique-id').val();

			ajax( 'insert', {
				name: name,
				code: code,
				unique_id: unique_id
			});

			return false;
		});
		
		$('a[data-id-to-remove]').click(function(){
			if( confirm( '<?php echo $text_are_you_sure; ?>' ) ) {
				var unique_id = $(this).attr('data-id-to-remove');

				ajax( 'remove', {
					unique_id: unique_id
				});
			}
			
			return false;
		});
		
		$('a[data-id-to-edit]').click(function(){
			var $tr = $(this).parent().parent();
			
			$tr.find('[data-val]').each(function(){
				var name = $(this).attr('data-val');
				
				$('#val-'+name).val( $(this).val() );
			});
			
			$tr.remove();
			
			return false;
		});
	})( jQuery );
</script>