<?php
	/*
	Template Name: Something
	*/
	//define('WP_USE_THEMES', true);
	require('../../../../wp-blog-header.php');
	require_once ('../api/bachbill_api.php');
	require_once('../functions.php');
	
	$action=$_GET['action'];
	if ($action=='list'){
		$activeOnly=(!$_GET['activeOnly'] || $_GET['activeOnly']=='true')?true:false;
		$api=getBachbillApi();
		$priceplanId=get_option('bachbill_priceplanId');
		$endUserAreaId=get_option('bachbill_endUserAreaId');
		$endUserId=getUserId();
		$res=$api->listSubscriptions($priceplanId, $endUserAreaId, $endUserId, $activeOnly);
		if ($api->hasErrors()){
			sendError($api->getErrorMessage());
			return;
		}
		$error='';
		if (!$res){
			$error=__('There was an error while trying to list the active subscriptions', 'bachbill');
		}
		if ($res['error']){
			$error=$res['error']['message'];
		}else {
			$res=$res['GetUserSubscriptionsResponse'];
			if ($res['code']==0){
				
			}else {
				$error=__('There was an error while trying to list the active subscriptions', 'bachbill');
			}
		}
		
		get_header();
		 ?>
			<script>
				function cancelSubscription(url){
					if (confirm("<?php _e('Are you sure that you want to cancel this subscription?', 'bachbill');?>")){
						document.location=url;
					}
				}
			</script>
			<div id="content">
				<?php echo $error; ?>
				<?php
//					if (!$error){
//						$res=$res['subscriptions'];
//						echo renderSubscriptions($res);
//					}
					if (!$error){
						$res=$res['subscriptions'];
//						echo 'sub: '.$res['Subscription']['id'].'</br>';
						if ($res['Subscription'] && !$res['Subscription']['id']){
							$res=$res['Subscription'];
						}
						echo renderSubscriptions($res);
//						foreach ($res as $x){
//							echo renderSubscriptions($x);
//						}
					}  
				?>
			</div>
		
			<?php 
			//get_sidebar(); 
					
			?>
			<?php 
			get_footer(); 
	}else if ($action=='cancel'){
		$api=getBachbillApi();
		$priceplanId=get_option('bachbill_priceplanId');
		$endUserAreaId=get_option('bachbill_endUserAreaId');
		$endUserId=getUserId();
		$subscriptionId=$_GET['subscriptionId'];
		
		$res=$api->calncelSubscription($priceplanId, $endUserAreaId, $endUserId, $subscriptionId);
		if ($api->hasErrors()){
			sendError($api->getErrorMessage());
			return;
		}
		$error='';
		if (!$res){
			$error=__('There was an error while trying to cancel the subscription', 'bachbill');
		}
		if ($res['error']){
			$error=$res['error']['message'];
		}else {
			$res=$res['PurchaseResponse'];
			if ($res['code']==0){
				$redirect='selfcare.php?action=list';
				wp_redirect($redirect);
				return;
			}else {
				$error=__('There was an error while trying to cancel the subscription', 'bachbill');
			}
		}
		get_header();
		 ?>
		
			<div id="content">
				<?php echo $error; ?>
				<?php
//					if (!$error){
//						$res=$res['subscriptions'];
//						$res=$res['Subscription'];
//						foreach ($res as $x){
//							echo renderSubscriptions($x);
//						}
//					} 
				?>
			</div>
		
			<?php 
			//get_sidebar(); 
					
			?>
			<?php 
			get_footer(); 
	}
		?>