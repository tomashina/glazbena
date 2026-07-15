<?php if ($full_width) { ?></div><?php } ?>
<div class="widget"<?php if ($use_margin) echo ' style="margin-bottom:' . $margin . '"'; ?>>
<div class="instagram_wrapper">
<?php if ($block_title) { ?>
<div class="instagram_title<?php if ($title_inline) echo ' floating_title text-center'; ?>">
<div class="table w100 h100"><div class="table-cell">
<div class="widget-title">
    <?php if ($title_preline) { ?><p class="pre-line"><?php echo $title_preline; ?></p><?php } ?>
    <?php if ($title) { ?> 
    <p class="main-title"><span><?php echo $title; ?></span></p>
    <p class="widget-title-separator"><i class="icon-line-cross"></i></p>
    <?php } ?>
    <?php if ($title_subline) { ?>
    <p class="sub-line"><span><?php echo $title_subline; ?></span></p>
    <?php } ?>
</div>
</div></div>
</div>
<?php } ?>
<div class="images_wrap">
<div id="instafeed<?php echo $module; ?>" class="grid-holder lg-grid<?php echo $columns; ?> md-grid<?php echo $columns_md; ?> sm-grid<?php echo $columns_sm; ?>" style="margin:-<?php echo $padding/2; ?>px;"></div>
</div>
</div> <!-- .instagram_wrapper -->      
</div>
<?php if ($full_width) { ?><div class="container"><?php } ?>

<script>
$( window ).load(function() { 		
		var feed = new Instafeed({
		get: 'user',
		userId: '<?php echo $username; ?>',
		accessToken: '<?php echo $access_token; ?>',
		target: 'instafeed<?php echo $module; ?>',
		limit: '<?php echo $limit; ?>',
		template: '<a href="{\{\link\}\}" target="_blank" class="item insta-item" style="padding:<?php echo $padding/2; ?>px;"><div style="position:absolute;top:<?php echo $padding/2; ?>px;right:<?php echo $padding/2; ?>px;bottom:<?php echo $padding/2; ?>px;left:<?php echo $padding/2; ?>px;background-image:url(\'{\{\image\}\}\');background-position:center center;background-size:cover;"></div><img src="catalog/view/theme/basel/image/insta-filler.png" /><span class="hover_fill" style="top:<?php echo $padding/2; ?>px;right:<?php echo $padding/2; ?>px;bottom:<?php echo $padding/2; ?>px;left:<?php echo $padding/2; ?>px;"></span></a>',
		<?php if ($resolution) { ?>
		resolution: 'standard_resolution'
		<?php } else { ?>
		resolution: 'low_resolution'
		<?php } ?>
		 });
feed.run();
});
</script>