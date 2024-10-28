<?php
	/*
	Template Name: Something
	*/
	//define('WP_USE_THEMES', true);
	require('../../../../wp-blog-header.php');
	require_once ('../api/bachbill_api.php');
	require_once('../functions.php');

	get_header();
 ?><div id="content">
 	<?php echo $_GET['message'];?><br/>
	<a href="javascript:history.back()"><?php _e('Go back', 'bachbill');?></a>
</div>

<?php 
//get_sidebar(); 
		
?>
<?php get_footer(); ?>