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
		$priceplanId=get_option('bachbill_priceplanId');
		$endUserAreaId=get_option('bachbill_endUserAreaId');
		$api=getBachbillApi();
//		$api->setParam('id', $bundle->id);
		$api->setParam('obj', 'transaction');
		$api->setParam('action', 'list');
		$api->setParam('endUserId', $endUserAreaId.'/'.getUserId());
		$res=$api->callOnIncomingUrlAction($priceplanId, '/quick_provision');
		if ($api->getErrorCode()<>0){
			wp_redirect($_SERVER['HTTP_REFERER'].'&bErrorStr='.urlencode($api->getErrorMessage()));
			return false;
		}
		$error='';
		if (!$res){
			$error=__('There was an error while trying to list the active subscriptions', 'bachbill');
		}
		if ($res['error']){
			$error=$res['error']['message'];
		}else {
			$res=$res['QuickProvisionResponse'];
			if ($res['code']==0){
				
			}else {
				$error=__('There was an error while trying to list the transactions', 'bachbill');
			}
		}
		
		get_header();
		 ?>
			<div id="content">
				<?php echo $error; ?>
				<?php
//					if (!$error){
//						$res=$res['subscriptions'];
//						echo renderSubscriptions($res);
//					}
					if (!$error){
						$res=$res['result'];
//						echo 'sub: '.$res['Subscription']['id'].'</br>';
						if ($res['Transaction'] && !$res['Transaction']['id']){
							$res=$res['Transaction'];
						}
						
						echo renderTransactions($res);
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
	}
		?>