<?php require DIR_TEMPLATE . 'extension/module/' . $_name . '-header.tpl'; ?>

<br />

<?php
	
		$_configurationName = $_name . '_settings';
		$_configurationValues = $settings;
		$_configurationMain = true;
		
		require DIR_TEMPLATE . 'extension/module/' . $_name . '-configuration.tpl';
	
	?>

<?php require DIR_TEMPLATE . 'extension/module/' . $_name . '-footer.tpl'; ?>