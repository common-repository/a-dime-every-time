<?php

require('../../../wp-load.php');
require_once('functions.php');
$fName=$_GET['file'];
$postId=$_GET['postId'];
$serviceId='s_'.$postId;
$link=get_option('bachbill_protected_'.$postId.'_'.$fName, '');
if (!$link){
	header("Status: 404 Not Found");
	return;
}
$authorized=false;
$user=wp_get_current_user();
$role=$user->roles[0];
if (is_user_logged_in() && $role && $role!='subscriber' ){
	$authorized=true;
	$user=wp_get_current_user();
}else {
	if (is_user_logged_in()){
		@session_start();
		$userServices=$_SESSION['bachbill_userServices'];
		if (!isset($userServices)){
			$userServices=array();
			$_SESSION['bachbill_userServices']=$userServices;
		}
		$service=$userServices[$serviceId];
		if ($service){
			$authorized=true;
		}else {
			$api=getBachbillApi();
			$priceplanId=get_option('bachbill_priceplanId');
			$endUserAreaId=get_option('bachbill_endUserAreaId');
			$res=$api->authorize($priceplanId, $endUserAreaId, getUserId(), $serviceId);//!!! see how to get the nUsages from the post
			if ($api->hasErrors()){
				echo $api->getErrorMessage();
			}
			if (!$res){
				echo __('There was an error while trying to authorize the user to use the content', 'bachbill');
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
						$authorized=true;
					}
					$subscriptionId=$res['subscriptions']['Subscription']['id'];
					
					$res=$api->usage($priceplanId, $endUserAreaId, getUserId(), $subscriptionId, $serviceId);//!!! see how to get the nUsages from the post
					$authorized=true;
					$service=new BachbillService($serviceId);
					$userServices[$serviceId]=$service;
					$_SESSION['bachbill_userServices']=$userServices;
				}else {
					if (!$res){
						echo __('There was an error while trying to authorize the user to use the content', 'bachbill');
					}
				}
			}
		}
	}
}
if (!$authorized){
	wp_redirect(getRootUrl().'?p='.$postId);
	return;
}
$file = file_get_contents($link, true);
$mimeType=getMimeType($fName);
if ($mimeType){
	header('Content-Type: '.$mimeType);
}
	
	echo $file;
?>