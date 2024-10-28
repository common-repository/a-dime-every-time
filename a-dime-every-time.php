<?php
/*
 Plugin Name: ADimeEveryTime
 Plugin URI: http://www.ADimeEveryTime.com
 Description: Wordpress content monetizing using ADimeEveryTime
 Author: Andoni Arostegi
 Author URI: http://ADimeEveryTime.com/
 Version: 1.6.5
 License: GPL (http://www.fsf.org/licensing/licenses/info/GPLv2.html)
 */

 if ( !defined('WP_CONTENT_URL') )
	define( 'WP_CONTENT_URL', get_option('siteurl') . '/wp-content');

$fbPluginUrl = plugins_url ( plugin_basename ( dirname ( __FILE__ ) ) );
define('FB_PLUGIN_URL', $fbPluginUrl);

require_once('bachbillAdminControls.php');
require_once('reporting.php');
require_once('payments.php');
require_once('api/bachbill_api.php');
require_once('functions.php');

/**
 * Runs when PLugin is activate
 * Use to install custom tables and options
 */
function bachbill_activate() {

}
register_activation_hook(__FILE__, 'bachbill_activate');

/**
 * Runs when PLugin is uninstall
 * Use to uninstall custom tables and options
 */
function bachbill_deactivate() {

}
register_deactivation_hook(__FILE__, 'bachbill_deactivate');


/**
 * function to display configration settings for facebook connect api
 *
 */
function bachbill_admin_menu() {

	add_menu_page("ADimeEveryTime plugin", "<span style='font-size: 8pt'>ADimeEveryTime</span>", 1, 'a-dime-every-time/admin.php', 'bachbill_general_options', plugins_url('a-dime-every-time/img/dime_logo_15x15.png'));
	add_submenu_page('a-dime-every-time/admin.php', __('Settings', 'bachbill'), __('Settings', 'bachbill'), 1, 'a-dime-every-time/admin.php','bachbill_general_options');
	add_submenu_page('a-dime-every-time/admin.php', __('Reporting', 'bachbill'), __('Reporting', 'bachbill'), 1, __('reporting', 'bachbill'), 'bachbill_reporting');
	$accountSetup=get_option('bachbill_account_setup');
	if ($accountSetup && $accountSetup=='ready'){
		add_submenu_page('a-dime-every-time/admin.php', __('Payments', 'bachbill'), __('Payments', 'bachbill'), 1, __('Payments', 'bachbill'), 'bachbill_payments');
	}
}

function rewriteLinks($post, $content){
	$links=get_post_meta($post->ID, 'bachbill_protected_links', '');
	if (!is_array($links)){
		return;
	}
	$links=$links[0];
	if ($links){
		$root=getRootUrl();
		foreach ($links as $link){
			$index=strrpos($link, '.');
			$name=md5($link);
			$ext='';
			if ($index>0){
				$ext=substr($link, $index);
			}
			$fName=$name.$ext;
			$fullName=$root.'/wp-content/plugins/a-dime-every-time/media.php?file='.$fName.'&postId='.$post->ID;
			update_option('bachbill_protected_'.$post->ID.'_'.$fName, $link);
			$content=str_replace($link, $fullName, $content);
			
		}
	}
	return $content;
}
function intercept($content){
	global $post;
	$isChargeable=get_post_meta($post->ID, 'bachbill_chargeable', true);
	if (!$isChargeable){
		// see if any category is chargeable
		$categories=wp_get_post_categories($post->ID);
		foreach ($categories as $cat){
			$isChargeable=get_option('bachbill_cat_'.$cat.'_chargeable');
			if ($isChargeable){
				break;
			}
		}
	}
	if (!$isChargeable){
		return $content;
	}

	$content=rewriteLinks($post, $content);
	$serviceId='s_'.$post->ID;
	
	$user=wp_get_current_user();
	$role=$user->roles[0];
	if (is_user_logged_in() && $role!='subscriber'){
		return $content;
	}
	if ( !is_user_logged_in() ){
		return $post->post_excerpt.'<br/>'.__('You are not logged in to see this content', 'bachbill').'.<br/><a href="'.getRootUrl().'/wp-login.php?redirect_to='.urlencode($_SERVER['REQUEST_URI']).'">'.__('log in', 'bachbill').'</a>';
		
	}else {
		if ($serviceId){
			@session_start();
			$userServices=$_SESSION['bachbill_userServices'];
			if (!isset($userServices)){
				$userServices=array();
				$_SESSION['bachbill_userServices']=$userServices;
			}
			$service=$userServices[$serviceId];
			if ($service){
				return $content;
			}else {
				// try to authorize the user
				// concurrence control. Some themes ask for the_content more than once and we don't want that
				@session_start();
				//!!! disable $concurrenceTime=$_SESSION['bachbill_concurrence_thecontent'];
				//!!! disable $_SESSION['bachbill_concurrence_thecontent']=microtime(true);
				//!!! disable if ($concurrenceTime>0 && microtime(true)-$concurrenceTime<0.7){
				//!!! disable 	return '';//.(microtime(true)-$concurrenceTime);
				//!!! disable }
				
				$nUsages=get_post_meta($post->ID, 'bachbill_n_usages', '1');
				$nUsages=$nUsages?$nUsages:1;
				if (is_single() || is_page()){
					$api=getBachbillApi();
					$priceplanId=get_option('bachbill_priceplanId');
					$endUserAreaId=get_option('bachbill_endUserAreaId');
					$api->setMethod("get");
					$res=$api->authorize($priceplanId, $endUserAreaId, getUserId(), $serviceId, $nUsages);
					if ($api->hasErrors()){
						return $api->getErrorMessage();
					}
					if (!$res){
						return __('There was an error while trying to authorize the user to use the content', 'bachbill');
					}
					if ($res['error']){
						echo $res['error']['message'];
					}else {
						$res=$res['AuthorizeResponse'];
						$transaction=$res['transaction'];
						if ($res['code']==0){
							if ($transaction){
								if ($transaction['captureDate']){
									// transaction is captured so user is authorized permanently. Otherwise it wouldn't be reported and only the subscription object would be there
									$service=new BachbillService($serviceId);
									$userServices[$serviceId]=$service;
									$_SESSION['bachbill_userServices']=$userServices;
								}else {
									// transaction is ongoing and not captured yet
								}
								return $content;
							}
							// if the subscription is credit based, show splash page
							$pendingAccesses=$res['subscriptions']['Subscription']['pendingAccesses'];
							if (!$_GET['confirmUsageSplash']=='true' && $pendingAccesses>0){
								$finalUrl=get_permalink( $post->ID );
								$position=strpos($finalUrl, '?');
								if ($position){
									$finalUrl.='&confirmUsageSplash=true';
								}else{
									$finalUrl.='?confirmUsageSplash=true';
								}
//								$finalUrl=strpos($finalUrl, '?')>=0?($finalUrl+'&confirmUsageSplash'):($finalUrl+'?confirmUsageSplash');
								return 
								'<br/><br/>'.
								__('This content costs', 'bachbill').
								' '.$nUsages.' '.
								__('credit', 'bachbill').($nUsages>1?'s':'').
								'<br/><br/>'.
								__('You have', 'bachbill').
								' '.$pendingAccesses.' '. 
								__('credits left in your subscription.', 'bachbill').
								'<br/><br/>'.
								__('Please hit', 'bachbill').
								' <a href="'.$finalUrl.'">'.
								__('Continue', 'bachbill').
								'</a>'.
								' '.
								__('to access the content and discount the credit from your balance.', 'bachbill');
								
							}else {
								$subscriptionId=$res['subscriptions']['Subscription']['id'];
								
								$res=$api->usage($priceplanId, $endUserAreaId, getUserId(), $subscriptionId, $serviceId, $nUsages);
								$service=new BachbillService($serviceId);
								$userServices[$serviceId]=$service;
								$_SESSION['bachbill_userServices']=$userServices;
								return $content;
							}
						}else {
							if (!$res){
								return __('There was an error while trying to authorize the user to use the content', 'bachbill');
							}
							
							
							@session_start();
							$_SESSION['bachbill_redirect_after_purchase']=get_permalink( $post->ID );
							return $post->post_excerpt.'<br/>'.renderPurchaseExperience($res['bundles']);
						}
						return 'Lets see...';
					}
				}else {
					$glue='';
					if ($post->post_excerpt){
						$glue='<br/><br/>';
					}
					$nUsages=get_post_meta($post->ID, 'bachbill_n_usages', '1');
					$credits=$nUsages>1?(__('This items costs', 'bachbill').' '.$nUsages.' '.__('credits', 'bachbill').'<br/>'):'';
					return $post->post_excerpt.$glue.$credits.__('Please press', 'bachbill').' <a href="'.get_permalink($post->ID).'">'.__('here', 'bachbill').'</a> '.__('to access the full content', 'bachbill').'<br/><br/>';
				}
			}
		}else {
			return $content;
		}
	}
}
//!!! disable $content_intercepted=false;
//!!! disable $thecontent='';
function content_interceptor($content){
	//!!! disable if (!$content_intercepted){
		$c=intercept($content);
		//!!! disable $thecontent=$c;
		//!!! disable $content_intercepted=true;
	//!!! disable }else{
		//!!! disable $c=$thecontent;
	//!!! disable }
	return $c;
}

function bachbill_logout(){
	@session_start();
	$endUserSessionId=$_SESSION['endUserSessionId'];
	unset($_SESSION['endUserSessionId']);
	unset($_SESSION['bachbill_userServices']);
	$_SESSION['bachbill_userServices']=array();
	@session_destroy();
	if ($endUserSessionId){
		$priceplanId=get_option('bachbill_priceplanId');
		
		$api=getBachbillApi();
		
		$ret= $api->endSession($priceplanId, $endUserSessionId);
		
		$ret=$ret['GetSessionResponse'];
	}else {
//		echo 'not logged in by bachbill..';
	}
}

/**
 * BachbillWidget Class
 */
class BachbillWidget extends WP_Widget {
    /** constructor */
    function BachbillWidget() {
        parent::WP_Widget(false, $name = 'A Dime Every Time');	
    }

    /** @see WP_Widget::widget */
    function widget($args, $instance) {		
        extract( $args );
        $title = apply_filters('widget_title', $instance['title']);
        ?>
              <?php echo $before_widget; ?>
                  <?php if ( $title )
                        echo $before_title . $title . $after_title; ?>
                  <div style="position: relative; right: 0px; top: 0px">
                  	<a href="<?php echo getRootUrl();?>/wp-content/plugins/a-dime-every-time/pages/selfcare.php?action=list"><?php _e('Manage my subscriptions', 'bachbill');?></a><br/>
                  	<a href="<?php echo getRootUrl();?>/wp-content/plugins/a-dime-every-time/pages/transactions.php?action=list"><?php _e('See my items', 'bachbill');?></a>
                  </div>
              <?php echo $after_widget; ?>
        <?php
    }

    /** @see WP_Widget::update */
    function update($new_instance, $old_instance) {				
	$instance = $old_instance;
	$instance['title'] = strip_tags($new_instance['title']);
        return $instance;
    }

    /** @see WP_Widget::form */
    function form($instance) {				
        $title = esc_attr($instance['title']);
        ?>
            <p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></label></p>
        <?php 
    }

} // class BachbillWidget


add_action('widgets_init', create_function('', 'return register_widget("BachbillWidget");'));


/* Function that outputs a message in the footer of the site. */
function boj_footer_message() {
	?>
	<div id="bbill_widget" style="display: none"><?php the_widget('BachbillWidget');?> </div>
	<script>
		document.getElementById("main").innerHTML=document.getElementById("bbill_widget").innerHTML+document.getElementById("main").innerHTML;
	</script>
	<?php 
}

function bachbill_edit_post_form($post){
	if( function_exists( 'add_meta_box' )) {
		add_meta_box( 'bachbill_sectionid', '<img style="vertical-align: middle" src="../wp-content/plugins/a-dime-every-time/img/dime_logo_30x30.png"/> '.__( 'ADimeEveryTime Charging Options', 'bachbill' ),
                'bachbill_edit_post_form2', 'post', 'advanced' );

	} else {
		add_action('dbx_post_advanced', 'bachbill_edit_post_form2' );
	}
	
}
function bachbill_edit_post_form2($post){
	$accountSetup=get_option('bachbill_account_setup');
	if ($accountSetup!='ready'){
		?><?php _e('If you want to use the ADimeEveryTime plugin pricing options please click', 'bachbill');?> <a href="admin.php?page=a-dime-every-time/admin.php"><?php _e('here', 'bachbill');?></a> <?php _e('set up the ADimeEveryTime plugin', 'bachbill');?><?php 
		return;
	}
	$pricepoints=null;
	$isChargeable=get_post_meta($post->ID, 'bachbill_chargeable', true);
	if ($isChargeable){
		$priceplanId=get_option('bachbill_priceplanId');
		$api=getBachbillApi();
		$res=$api->callOnIncomingUrlAction($priceplanId, '/quick_provision?obj=bundle&action=get&id=s_'.$post->ID);
		if ($api->getErrorCode()<>0){
			showErrorWithAlert($api->getErrorMessage()); 
			return;
		}
		$code=$res['QuickProvisionResponse']['code'];
		if ($code==-4){// not existing yet
		}else if ($code==0){
			$pricepoints=$res['QuickProvisionResponse']['result']['pricepoints'];
			if ($pricepoints['Pricepoint'] && !$pricepoints['Pricepoint']['id']){
				$pricepoints=$pricepoints['Pricepoint'];
			}
		}
	}
	bachbill_print_pricing_options('post', $isChargeable, $pricepoints);
}
function bachbill_save_post($post_id){
	if ( !current_user_can('edit_post', $post_id) ){
		return $post_id;
	}
	$currency=get_option('bachbill_currency');
	if (sizeof($_POST)==0){
		return $post_id;
	}
	$quickEdit=!$_POST['original_post_status'];// it's quick edit
	$nUsages=$_POST['nUsages'];
	if ($nUsages){
	//add_post_meta($post_id, 'bachbill_n_usages', $nUsages) or 
    	update_post_meta($post_id, 'bachbill_n_usages', $nUsages);
	}
	
	$post=get_post($post_id);
	@session_start();
	$wasChargeable=$_SESSION['wasChargeable'];
	unset($_SESSION['wasChargeable']);
	if ($post->post_status=='publish'){
		$prevTitle=$_SESSION['prevTitle'];
		unset($_SESSION['prevTitle']);
		if ($_POST['postPricingChanges']=='true' || $prevTitle!=$post->post_title){ // the post name or pricing changed
			$isChargeable=$quickEdit?get_post_meta($post->ID, 'bachbill_chargeable', true):$_POST['isChargeable'];
			if (!$isChargeable && $wasChargeable){
				$priceplanId=get_option('bachbill_priceplanId');
				$api=getBachbillApi();
				$api->setParam('id', 's_'.$post->ID);
				$api->setParam('obj', 'service');
				$api->setParam('action', 'deprovision');
				$res=$api->callOnIncomingUrlAction($priceplanId, '/quick_provision');
				if ($api->getErrorCode()<>0){
					showErrorWithAlert($api->getErrorMessage()); 
					return;
				}
			}
			if (!$quickEdit){
				add_post_meta($post_id, 'bachbill_chargeable', $isChargeable, true) or 
	        	    update_post_meta($post_id, 'bachbill_chargeable', $isChargeable);
			}
			if (!$quickEdit && $isChargeable){
		    	// manage price
				$price=$_POST['price'];
				$priceplanId=get_option('bachbill_priceplanId');
				$api=getBachbillApi();
				$api->setParam('id', 's_'.$post->ID);
				$api->setParam('obj', 'service');
				$api->setParam('action', 'provision');
				$api->setParam('description', $post->post_title);
				$api->setParam('price', $price);
				$api->setParam('currency', $currency);
				$res=$api->callOnIncomingUrlAction($priceplanId, '/quick_provision');
				if ($api->getErrorCode()<>0){
					wp_redirect($_SERVER['HTTP_REFERER'].'&bErrorStr='.urlencode($api->getErrorMessage()));
					throw new Exception($api->getErrorMessage(), $api->getErrorCode());
				}
			}
		}
		// manage protected links
		$links=$_POST['bachbill_links'];
		update_post_meta($post->ID, 'bachbill_protected_links', $links);
    	
    	// check if categories changed
		$categories=wp_get_post_categories($post_id);
    	$previous_categories=$_SESSION['bachbill_previous_categories'];
		unset($_SESSION['bachbill_previous_categories']);
		if (!is_array($categories)){
			$c=$categories;
			$categories=array();
			array_push($categories, $c);
		}
		if (!is_array($previous_categories)){
			$c=$previous_categories;
			$previous_categories=array();
			array_push($previous_categories, $c);
		}
		$added=array();
		$deleted=array();
//		foreach ($categories as $cat){
//			if ((''.array_search($cat, $previous_categories))===''){
//				// not there previously
//				$catName=get_cat_name($cat);
//				$isChargeable=get_option('bachbill_cat_'.$cat.'_chargeable');
//				if ($isChargeable){
//					//bachbill_update_category($cat, false);
//					array_push($added, 'b_'.$cat);
//				}
//			}
//		}
		foreach ($categories as $cat){ // we will add ALL the existing categories, even if there wheren't before. This way, we avoid draft posts to have "orphan" categories that are not provisioned in bachbill
			$catName=get_cat_name($cat);
			$isChargeable=get_option('bachbill_cat_'.$cat.'_chargeable');
			if ($isChargeable){
				//bachbill_update_category($cat, false);
				array_push($added, 'b_'.$cat);
			}
		}
    	foreach ($previous_categories as $cat){
    		if ((''.array_search($cat, $categories))===''){
    			// cat not there anymore
    			$catName=get_cat_name($cat);
    			$isChargeable=get_option('bachbill_cat_'.$cat.'_chargeable');
				if ($isChargeable){
					//bachbill_update_category($cat, false);
					array_push($deleted, 'b_'.$cat);
				}
			}
		}
		if (sizeof($added)+sizeof($deleted)>0){
			if (!addEditServiceBundles('s_'.$post->ID, $post->post_title, $added, $deleted)){
				throw new Exception('dd', -1);
			}
		}
		
	}else {
		// save previous categories
		//exec( 'echo "saving prev id '.$post->ID.'">>/tmp/php.log');
		@session_start();
		$parent_post_id=$post->post_parent;
		if ($parent_post_id>0){
			$categories=wp_get_post_categories($parent_post_id);
			$_SESSION['bachbill_previous_categories']=$categories;
			$wasChargeable=get_post_meta($post->ID, 'bachbill_chargeable', true);
			$_SESSION['wasChargeable']=wasChargeable;
		}
		// save previous title
		$_SESSION['prevTitle']=$post->post_title;
	}
	return $post_id;
}
function fill_pricepoints($pricepoints){
	$i=0;
	foreach ($pricepoints as $p){
		?>
		addPricepoint();
		<?php
		if (strpos($p['id'], 'a_timerecurring_')===0){
			echo 'toggleBold('.$i.',"a");';
			echo 'document.getElementById("a_bachbill_type_'.$i.'").checked=true;';
			echo 'document.getElementById("a_expireDuration_'.$i.'").value="'.$p['expireDuration'].'";';
			echo 'document.getElementById("a_price_'.$i.'").value="'.$p['price'].'";';
			echo 'document.getElementById("a_expireDurationUnit_'.$i.'").value="'.$p['expireDurationUnit'].'";';
		}else if (strpos($p['id'], 'b_uses_')===0){
			echo 'document.getElementById("b_bachbill_type_'.$i.'").checked=true;';
			echo 'toggleBold('.$i.',"b");';
			echo 'document.getElementById("b_accessDuration_'.$i.'").value="'.$p['accessDuration'].'";';
			echo 'document.getElementById("b_price_'.$i.'").value="'.$p['price'].'";';
		}else if (strpos($p['id'], 'c_time_')===0){
			echo 'document.getElementById("c_bachbill_type_'.$i.'").checked=true;';
			echo 'toggleBold('.$i.',"c");';
			echo 'document.getElementById("c_expireDuration_'.$i.'").value="'.$p['expireDuration'].'";';
			echo 'document.getElementById("c_price_'.$i.'").value="'.$p['price'].'";';
			echo 'document.getElementById("c_expireDurationUnit_'.$i.'").value="'.$p['expireDurationUnit'].'";';
		}else if (strpos($p['id'], 'd_timeanduses_')===0){
			echo 'document.getElementById("d_bachbill_type_'.$i.'").checked=true;';
			echo 'toggleBold('.$i.',"d");';
			echo 'document.getElementById("d_accessDuration_'.$i.'").value="'.$p['accessDuration'].'";';
			echo 'document.getElementById("d_expireDuration_'.$i.'").value="'.$p['expireDuration'].'";';
			echo 'document.getElementById("d_price_'.$i.'").value="'.$p['price'].'";';
			echo 'document.getElementById("d_expireDurationUnit_'.$i.'").value="'.$p['expireDurationUnit'].'";';
		}
		$i++;
	}
}
function bachbill_print_pricing_options($mode, $isChargeable, $pricepoints){
	$currency=get_option('bachbill_currency');

	?>
	<script>
		function showPricingOptions(){
			document.getElementById("pricingOptions").style.display='';
		}
		function hidePricingOptions(){
			document.getElementById("pricingOptions").style.display='none';
		}
		function togglePricing(ch){
			document.getElementById("postPricingChanges").value="true";
			if (ch.checked){
				showPricingOptions();
			}else {
				hidePricingOptions();
			}
		}
		function validateNumber(nr){
			if (nr-nr!=0){
				return false;
			}
			return true;
		}
		function validateDecimal(price, factor){
			var priceStr=""+price*factor;
			if (priceStr.indexOf(".")>0){
				return false;
			}
			return true;
		}
		function validatePrice(price){
			if (!validateNumber(price)){
				alert("<?php _e('Invalid price', 'bachbill');?> "+price);
				return false;
			}
			if (!validateDecimal(price, 100)){
				alert("<?php _e('Price should only contain 2 decimals', 'bachbill');?>");
				return false;
			}
			return true;
		}
		function exitForm(eventObject){
			if (eventObject.preventDefault) {
		        eventObject.preventDefault();
		    } else if (window.event) /* for ie */ {
		        window.event.returnValue = false;
		    }
		}
		var divIndex=0; // always increases, never decreases. Limit is 50, when one is deleted it gets disabled
	</script>
	<input type="hidden" id="postPricingChanges" name="postPricingChanges" value="false"/>
	<?php
		if ($mode=='category'){
			echo '<br/><h2><img style="vertical-align: middle" src="../wp-content/plugins/a-dime-every-time/img/dime_logo_30x30.png"/>  '.__( 'ADimeEveryTime Charging Options', 'bachbill' ).'</h2><br/>';
		}
	?>
	<?php
	$bErrorStr=$_GET['bErrorStr'];
	if ($bErrorStr){
		showErrorWithAlert($bErrorStr); 
	}
	?>
	<?php
		if ($mode=='post'){
		$nUsages=get_post_meta(get_the_ID(), 'bachbill_n_usages', '1');
		$nUsages=$nUsages?$nUsages:1;
			?>
	<br/>
	&nbsp;&nbsp;&nbsp;<?php _e('Number of usages', 'bachbill');?>: <input class="border" type="text" size="1" id="nUsages" name="nUsages" value="<?php echo $nUsages;?>"/> <?php _e('If the article is used by a subscription that discounts usages (i.e. a 10 usages subscription), this is the number of usages it will discount to the subscription', 'bachbill');?>.
	<br/><br/>
	<?php }
	?>
	<input type="hidden" name="wasChargeable" value="<?php echo $isChargeable;?>"/>
	<input type="checkbox" name="isChargeable" onchange="togglePricing(this)" value="1" <?php echo($isChargeable==1?'checked="1"':''); ?>/> <b><?php _e('This content is chargeable', 'bachbill');?></b> <?php if ($mode!='category'){?><br/>(<?php  _e('If the content belongs to a category that is chargeable, there is no need to set it as chargeable, unless you want to assign a price to it explicitely', 'bachbill');?>)<?php }?><br/><br/>
	<br/>
	<input type="hidden" id="bachbill_n_pricepoints" name="bachbill_n_pricepoints" value="0"/>
	<div id="pricepoint_template" style="display: none">
		<input type="hidden" id="bachbill_pricepoint_enabled___index__" name="bachbill_pricepoint_enabled___index__" value="true"/>
		<br/>
		<div style="padding: 10px; border: solid 2px #cccccc; height: 100%">
			<?php _e('Choose below', 'bachbill');?>:<br/>
			<table>
				<tr>
					<td>
						<span id="spn_a___index__" style="font-weight: bold">
							<input type="radio" id="a_bachbill_type___index__" name="bachbill_type___index__" onchange="toggleBold(__index__, 'a')" checked="1" value="timerecurring"/> <?php _e('See any post in this category', 'bachbill');?> 
							<?php _e('for', 'bachbill');?> <input class="border" type="text" size="1" id="a_price___index__" name="a_price___index__" value=""/> <?php echo $currency;?> <?php _e('for', 'bachbill');?> <input class="border" type="text" size="1" id="a_expireDuration___index__" name="a_expireDuration___index__" value="1"/>
							<select id="a_expireDurationUnit___index__" name="a_expireDurationUnit___index__">
								<option value="month"><?php _e('month', 'bachbill');?></option>
								<option value="day"><?php _e('day', 'bachbill');?></option>
								<option value="week"><?php _e('week', 'bachbill');?></option>
							</select>, <?php _e('renewed automatically', 'bachbill');?>
							<br/><br/>
						</span>
						<span id="spn_b___index__">
							<input type="radio" id="b_bachbill_type___index__" name="bachbill_type___index__" onchange="toggleBold(__index__, 'b')" value="uses"/> <?php _e('See', 'bachbill');?> <input class="border" type="text" size="1" id="b_accessDuration___index__" name="b_accessDuration___index__" value=""/> <?php _e('posts', 'bachbill');?> <?php _e('for', 'bachbill');?> <input class="border" type="text" size="1" id="b_price___index__" name="b_price___index__" value=""/> <?php echo $currency;?><br/><br/>
						</span>
						<span id="spn_c___index__">
							<input type="radio" id="c_bachbill_type___index__" name="bachbill_type___index__" onchange="toggleBold(__index__, 'c')" value="time"/> <?php _e('See any post in this category', 'bachbill');?> <?php _e('during', 'bachbill');?> <input class="border" type="text" size="1" id="c_expireDuration___index__" name="c_expireDuration___index__" value="1"/>
							<select id="c_expireDurationUnit___index__" name="c_expireDurationUnit___index__">
								<option value="month"><?php _e('month', 'bachbill');?></option>
								<option value="day"><?php _e('day', 'bachbill');?></option>
								<option value="week"><?php _e('week', 'bachbill');?></option>
							</select>
							<?php _e('for', 'bachbill');?> <input class="border" type="text" size="1" id="c_price___index__" name="c_price___index__" value=""/> <?php echo $currency;?><br/><br/>
						</span>
						<span id="spn_d___index__">
							<input type="radio" id="d_bachbill_type___index__" name="bachbill_type___index__" onchange="toggleBold(__index__, 'd')" value="timeanduses"/> <?php _e('See', 'bachbill');?> <input class="border" type="text" size="1" id="d_accessDuration___index__" name="d_accessDuration___index__" value=""/> <?php _e('posts', 'bachbill');?> <?php _e('during', 'bachbill');?>
							<input class="border" type="text" size="1" id="d_expireDuration___index__" name="d_expireDuration___index__" value="1"/>
							<select id="d_expireDurationUnit___index__" name="d_expireDurationUnit___index__">
								<option value="month"><?php _e('month', 'bachbill');?></option>
								<option value="day"><?php _e('day', 'bachbill');?></option>
								<option value="week"><?php _e('week', 'bachbill');?></option>
							</select>
							<?php _e('for', 'bachbill');?> <input class="border" type="text" size="1" id="d_price___index__" name="d_price___index__" value=""/> <?php echo $currency;?><br/><br/>
						</span>
					</td>
					
				</tr>
			</table>
			<a href="javascript:hidePricepoint(__index__)"><?php _e('Delete this option', 'bachbill');?></a>
		</div>
	</div>
	<div id="pricingOptions" style="margin:2em; padding: 2em; background-color: #EEE; border-radius: 1em; display: <?php echo ($isChargeable?'': 'none');?>">
		<h2><img width="32" style="vertical-align: middle" src="../wp-content/plugins/a-dime-every-time/img/puzzle.png"/> <?php _e('Pricing options', 'bachbill');?></h2>
		<input type="hidden" name="bachbill_mode" value="<?php echo $mode;?>"/>
		<?php
		if ($mode=='post'){
				$price=$pricepoints['Pricepoint']['price'];
				?>
				&nbsp;&nbsp;&nbsp;<?php _e('Price', 'bachbill');?>: <input class="border" onChange="document.getElementById('postPricingChanges').value='true';" type="text" size="1" id="price" name="price" value="<?php echo $price;?>"/> <?php echo $currency;?>
				<?php
			}else if ($mode=='category'){
				?>
				<br/>
				<div id="pricing_holder">
					<?php
						for ($i = 0; $i < 50; $i++) {
						    ?>
						    <div id="bachbill_price_<?php echo $i;?>"></div>
						    <?php
						}
					?>
				</div>
				<script>
					function replaceAll(txt, replace, with_this) {
					  return txt.replace(new RegExp(replace, 'g'),with_this);
					}
					function addPricepoint(){
						var str=document.getElementById("pricepoint_template").innerHTML;
						str=replaceAll(str, "__index__", divIndex);
						document.getElementById("bachbill_price_"+divIndex).innerHTML=str;
						divIndex++;
						document.getElementById("bachbill_n_pricepoints").value=divIndex;
					}
					function hidePricepoint(index){
						document.getElementById("bachbill_price_"+index).style.display='none';
						document.getElementById("bachbill_pricepoint_enabled_"+index).value=false;
					}
					function toggleBold(index, item){
						document.getElementById("spn_a_"+index).style.fontWeight='normal';
						document.getElementById("spn_b_"+index).style.fontWeight='normal';
						document.getElementById("spn_c_"+index).style.fontWeight='normal';
						document.getElementById("spn_d_"+index).style.fontWeight='normal';

						document.getElementById("spn_"+item+"_"+index).style.fontWeight='bold';
					}

					function validateCategoryPricingOptions(eventObject){
						
						for (var i=0; i<divIndex; i++){
							if (document.getElementById("bachbill_pricepoint_enabled_"+i).value==true){
								//!!! follow here
							}
						}

						/*if (!validatePrice(price)){
							exitForm(eventObject);
						}*/
						return true;
					}
					
					var f=document.getElementById("edittag")
					if (f.addEventListener){                 
		                f.addEventListener('submit', validateCategoryPricingOptions, false); 
			        } else if (f.attachEvent){                       
			                f.attachEvent('onsubmit', validateCategoryPricingOptions);
			        }
			        
					
					<?php
					if (!is_array($pricepoints) || count($pricepoints)===0){
						?>
						addPricepoint();//no pricepoints present
						<?php
					}else {
						fill_pricepoints($pricepoints);
					}
					
					?>
				</script>
				<br/><br/>
				<a href="javascript:addPricepoint()"><?php _e('Add another option', 'bachbill');?></a>
				<?php
			} 
		?>
		<?php
		if ($mode=='post'){
			?>
		<div>
			<br/>
			<h2><img style="vertical-align: middle" src="../wp-content/plugins/a-dime-every-time/img/media_protect.png"/> <?php _e('Media link protection', 'bachbill');?></h2><br/>
			<?php _e('Protecting media links is a mechanism to prevent users from sharing links of images, music tracks, e-pub files, etc. which are suppossed to be chargeable. It\'s not recommended doing it for trivial pieces of contents like static icons, etc. since protected content serving is a bit slower than the open one.', 'bachbill');?>
			<br/><br/>
			<h3><b><?php _e('Select the links to protect', 'bachbill');?>:</b></h3><br/><br/>
			<div id="link_holder">
			</div>
			<script>
				<?php
				 	$links=get_post_meta(get_the_ID(), 'bachbill_protected_links', '');
				 	if (is_array($links)){
				 		$links=$links[0];
				 	}
				?>
				
				function validatePricingOptions(eventObject){
					var price=document.getElementById("price").value;
					if (!validatePrice(price)){
						exitForm(eventObject);
					}
					return true;
				}
				var f=document.getElementById("post")
				if (f.addEventListener){                 
	                f.addEventListener('submit', validatePricingOptions, false); 
		        } else if (f.attachEvent){                       
		                f.attachEvent('onsubmit', validatePricingOptions);
		        }
				
				var protectedLinks="<?php echo getArrayAsString($links);?>"+",";// to make search more robust
				function removeDuplicateElement(arrayName)
				{
					if (arrayName==null) return new Array();
				  var newArray=new Array();
				  label:for(var i=0; i<arrayName.length;i++ )
				  {  
				  for(var j=0; j<newArray.length;j++ )
				  {
				  if(newArray[j]==arrayName[i]) 
				  continue label;
				  }
				  newArray[newArray.length] = arrayName[i];
				  }
				  return newArray;
				 }
				function getLinks(){					
					var content=document.getElementById("content_ifr")?(document.getElementById("content_ifr").contentDocument?document.getElementById("content_ifr").contentDocument.body.innerHTML:window.frames["content_ifr"].document.body.innerHTML):document.getElementById("content").value;
					var links=content.match(/(href|src)=[\"']([^\"']*)[\"']/g);
					var newLinks=removeDuplicateElement(links);
					return newLinks;
				}
				
				function refreshLinks(){
					var links=getLinks();
					var str="";
					for (i=0; i<links.length; i++){
						var index=links[i].indexOf('=')+2;
						var lnk=links[i].substring(index, links[i].length-1);
						var prevChk=document.getElementById(lnk);
						var chkStr='';
						if (prevChk){
							chkStr=prevChk.checked?"checked='1'":"";
						}else {
							chkStr=(protectedLinks.indexOf(lnk+",")>=0)?"checked='1'":"";
						}
						str+="<input id='"+lnk+"' type='checkbox' name='bachbill_links[]' value='"+lnk+"' "+chkStr+"/> "+lnk+"<br/><br/>";
					}
					if (str==""){
						str="<?php _e('No links available for this post', 'bachbill');?><br/><br/>";
					}
					document.getElementById("link_holder").innerHTML=str;
				}
				refreshLinks();
			</script>
			<input type="button" onClick="refreshLinks()" value="<?php _e('Refresh links from content', 'bachbill');?>"/>
		</div>
		<?php
		}?>
	</div>
	<?php if ($mode=='post'){
	?>
	<table width="100%">
	<tr><td align="right">
	<!--<input name="save" type="submit" class="button-primary" id="publish2" value="<?php _e('Update Post', 'bachbill');?>">-->
	</td></tr>
	</table>
	<?php
	}
}
function bachbill_edit_category_form($cat){
	$accountSetup=get_option('bachbill_account_setup');
	if ($accountSetup!='ready'){
		?>If you want to use the ADimeEveryTime plugin pricing options please click <a href="admin.php?page=a-dime-every-time/admin.php"><?php _e('here', 'bachbill');?></a> <?php _e('set up the ADimeEveryTime plugin', 'bachbill');?><?php 
		return;
	}
	if ($cat->term_id){
//		if ($cat->term_id!=1){
			$isChargeable=get_option('bachbill_cat_'.$cat->term_id.'_chargeable');
			if ($isChargeable){
				$priceplanId=get_option('bachbill_priceplanId');
				$api=getBachbillApi();
				$res=$api->callOnIncomingUrlAction($priceplanId, '/quick_provision?obj=bundle&action=get&id=b_'.$cat->term_id);
				//exec( 'echo "res '.$res.'">>/tmp/php.log');
				if ($api->getErrorCode()<>0){
					showErrorWithAlert($api->getErrorMessage()); 
					return;
				}
				$code=$res['QuickProvisionResponse']['code'];
				if ($code==-4){// not existing yet
				}else if ($code==0){
					$pricepoints=$res['QuickProvisionResponse']['result']['pricepoints'];
					if ($pricepoints['Pricepoint'] && !$pricepoints['Pricepoint']['id']){
						$pricepoints=$pricepoints['Pricepoint'];
					}
				}
			}
//		}
//		if ($cat->term_id!=1){
			bachbill_print_pricing_options('category', $isChargeable, $pricepoints);
//		}
	}
}
function hasService($bundle, $service){
	foreach ($bundle->services as $s){
		if ($service->id == $s->id){
			return true;
		} 
	}
	return false;
}
function bachbill_update_category($term_id, $managePricepoints){
	$currency=get_option('bachbill_currency');
	$bundle=provisionBundle($term_id);
	$posts=get_posts(array('category'=>$term_id, 'numberposts' => 10000));
	$changed=false;
	$bundle->services=array();
	foreach($posts as $post){
		// make all posts chargeable and provision them
		$service=provisionService($post->ID);
		array_push($bundle->services, $service);
		$changed=true;
	}
	if ($managePricepoints){
		// manage price
		$price=$_POST['price'];
		$nPricepoints=$_POST['bachbill_n_pricepoints'];
		$pricepoints=array();
		for ($i=0; $i<$nPricepoints; $i++){
			$bachbill_pricepoint_enabled=$_POST['bachbill_pricepoint_enabled_'.$i]=='true';
			if ($bachbill_pricepoint_enabled){
				$bachbill_type=$_POST['bachbill_type_'.$i];
				if ($bachbill_type=='timerecurring'){
					$price=$_POST['a_price_'.$i];
					$expireDuration=$_POST['a_expireDuration_'.$i];
					$expireDurationUnit=$_POST['a_expireDurationUnit_'.$i];
					$pricepointId='a_timerecurring_'.$price.'_'.$currency.'_'.$expireDuration.'_'.$expireDurationUnit;
					$desc=$expireDuration.' '.$expireDurationUnit;
					$pricepoint=provisionPricepoint($pricepointId, $price, $currency, '-1', '', $expireDuration, $expireDurationUnit, true, $desc);
					if (!$pricepoint->extendedAttributes){
						$pricepoint->extendedAttributes=array();
					}
					array_push($pricepoints, $pricepoint);
				}else if ($bachbill_type=='uses'){
					$accessDuration=$_POST['b_accessDuration_'.$i];
					$price=$_POST['b_price_'.$i];
					$pricepointId='b_uses_'.$price.'_'.$currency.'_'.$accessDuration.'_use';
					$desc=$accessDuration.' use';
					$pricepoint=provisionPricepoint($pricepointId, $price, $currency, $accessDuration, 'use', 0, '', false, $desc);
					if (!$pricepoint->extendedAttributes){
						$pricepoint->extendedAttributes=array();
					}
					array_push($pricepoints, $pricepoint);
				}else if ($bachbill_type=='time'){
					$price=$_POST['c_price_'.$i];
					$expireDuration=$_POST['c_expireDuration_'.$i];
					$expireDurationUnit=$_POST['c_expireDurationUnit_'.$i];
					$pricepointId='c_time_'.$price.'_'.$currency.'_'.$expireDuration.'_'.$expireDurationUnit;
					$desc=$expireDuration.' '.$expireDurationUnit;
					$pricepoint=provisionPricepoint($pricepointId, $price, $currency, '-1', '', $expireDuration, $expireDurationUnit, false, $desc);
					if (!$pricepoint->extendedAttributes){
						$pricepoint->extendedAttributes=array();
					}
					array_push($pricepoints, $pricepoint);
				}else if ($bachbill_type=='timeanduses'){
					$price=$_POST['d_price_'.$i];
					$accessDuration=$_POST['d_accessDuration_'.$i];
					$expireDuration=$_POST['d_expireDuration_'.$i];
					$expireDurationUnit=$_POST['d_expireDurationUnit_'.$i];
					$pricepointId='d_timeanduses_'.$price.'_'.$currency.'_'.$accessDuration.'_use_'.$expireDuration.'_'.$expireDurationUnit;
					$desc=$expireDuration.' '.$expireDurationUnit;
					$pricepoint=provisionPricepoint($pricepointId, $price, $currency, $accessDuration, 'use', $expireDuration, $expireDurationUnit, false, $desc);
					if (!$pricepoint->extendedAttributes){
						$pricepoint->extendedAttributes=array();
					}
					array_push($pricepoints, $pricepoint);
				}
				
			}
		}
		$bundle->pricepoints=$pricepoints;
	}
	if (!saveBundle($bundle, $managePricepoints)){
		throw new Exception('dd', -1);
	}
}
function bachbill_save_category($term_id){
	$isChargeable=$_POST['isChargeable'];
	$wasChargeable=$_POST['wasChargeable'];
	if (!$isChargeable && $wasChargeable){
		if (!deleteBundle('b_'.$term_id)){
			throw new Exception('dd', -1);
		}
	}
	$nPricepoints=$_POST['bachbill_n_pricepoints']; // if not present means that it's quick edit
	if ($nPricepoints){
		if ($isChargeable){
			bachbill_update_category($term_id, true);
			// do it after updating, just in case
			update_option('bachbill_cat_'.$term_id.'_chargeable', $isChargeable);
		}else {
			update_option('bachbill_cat_'.$term_id.'_chargeable', $isChargeable);
		}
	}
	return $term_id;
}

function bachbill_init() {
	$plugin_dir = dirname( plugin_basename( __FILE__ ) );
	load_plugin_textdomain( 'bachbill', false, $plugin_dir );
}

/**
 * WP HOOKS
 **/
add_action('init', 'bachbill_init');
add_action('admin_menu', 'bachbill_admin_menu');
add_action('the_content', 'content_interceptor');
add_action('register_form','plugin_form');
add_action('login_footer','bachbill_login_footer');
add_action('wp_logout','bachbill_logout');
add_action('dbx_post_advanced', 'bachbill_edit_post_form' );
add_action('save_post', 'bachbill_save_post' );
//add_action('publish_post', array('hsdhb', 'hhhsd'));
add_action('edit_category_form', 'bachbill_edit_category_form' );
add_action('edit_category', 'bachbill_save_category' );
/**
 * END WP HOOKS
 **/


function bachbill_login_footer(){
//	$referer=$_SERVER['HTTP_REFERER'];
//	if (!empty($referer) && !strpos($referer, '/wp-login.php')){
//		@session_start();
//		$_SESSION['bachbill_redirect_after_login']=$referer;
//	}else {
//		unset($_SESSION['bachbill_redirect_after_login']);
//	}
	$socialNetworks=get_option('bachbill_social_networks');
	$n=0;
	foreach ($socialNetworks as $key => $value){
		if ($value){
			$n++;
		}
	}
	?>
 			
 			
 				<div width="100%" align="center">

<table class="logintbl" width="600">
	<tr>
		<td colspan="2"></td>
	</tr>
	<?php if ($n>0){
	?>
	<tr>
		<td valign="top" colspan="6">If you prefer to log in using your social network please choose below:</td>
	</tr>
	<?php 
	}
	?>
	<tr>
		<td align="center"><?php if ($socialNetworks['facebook']){?><a href="<?php echo getRootUrl();?>/wp-content/plugins/a-dime-every-time/pages/login_driver.php?action=requestSession&provider=facebook&redirect_to=<?php echo urldecode($_GET['redirect_to']);?>"><img style="border: 0px" src="wp-content/plugins/a-dime-every-time/img/facebook.png"/></a><?php }?></td>
		<td align="center"><?php if ($socialNetworks['linkedin']){?><a href="<?php echo getRootUrl();?>/wp-content/plugins/a-dime-every-time/pages/login_driver.php?action=requestSession&provider=linkedin&redirect_to=<?php echo urldecode($_GET['redirect_to']);?>"><img style="border: 0px" src="wp-content/plugins/a-dime-every-time/img/linkedin.png"/></a><?php }?></td>
	
		<td align="center"><?php if ($socialNetworks['twitter']){?><a href="<?php echo getRootUrl();?>/wp-content/plugins/a-dime-every-time/pages/login_driver.php?action=requestSession&provider=twitter&redirect_to=<?php echo urldecode($_GET['redirect_to']);?>"><img style="border: 0px" src="wp-content/plugins/a-dime-every-time/img/twitter.png"/></a><?php }?></td>
		<td align="center"><?php if ($socialNetworks['google']){?><a href="<?php echo getRootUrl();?>/wp-content/plugins/a-dime-every-time/pages/login_driver.php?action=requestSession&provider=google&redirect_to=<?php echo urldecode($_GET['redirect_to']);?>"><img style="border: 0px" src="wp-content/plugins/a-dime-every-time/img/google.png"/></a><?php }?></td>
	
		<td align="center"><?php if ($socialNetworks['yahoo']){?><a href="<?php echo getRootUrl();?>/wp-content/plugins/a-dime-every-time/pages/login_driver.php?action=requestSession&provider=yahoo&redirect_to=<?php echo urldecode($_GET['redirect_to']);?>"><img style="border: 0px" src="wp-content/plugins/a-dime-every-time/img/yahoo.png"/></a><?php }?></td>
		
	</tr>
	

	
	
</table>
 					</p>
</div>
<?php 
}

?>