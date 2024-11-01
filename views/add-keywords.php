<div class="wrap">
	<?php screen_icon(); ?>
	<h2>Add Keywords</h2>

	<p>Enter one keyword per line:</p>

	<form action="<?php echo esc_attr(add_query_arg('noheader', 1)); ?>" method="post">
		<textarea rows="10" cols="60" name="keywords" id="keywords"></textarea>
		<?php submit_button('Add Keywords'); ?>
	</form>

</div>
