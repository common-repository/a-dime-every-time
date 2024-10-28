<?php
/*
Template Name: Something
*/
//define('WP_USE_THEMES', true);
require('../../../../wp-blog-header.php');
require_once ('../api/bachbill_api.php');
require_once('../functions.php');

	function override_login($user, $username, $password) {
		return new WP_User($username);
	}
	
	
//	function social_signon($userId, $name, $email){
////		echo 'userId: '.$userId;
//		add_filter('authenticate', 'override_login', 30, 3);
//		
//		$userObj = username_exists( $userId );
//		echo 'uid: '.$userId;
//		if ( !$userObj ) {
//			echo 'not existing';
//			$random_password = wp_generate_password( 12, false );
//			$userObj = wp_create_user( $userId, $random_password, $email );
//			echo 'just created --> '.$userObj->id;
//		} else {
//			$random_password = __('User already exists.  Password inherited.');
//		}
//		
//		
//		$creds = array();
//		$creds['user_login'] = $userId;
//		//$creds['user_password'] = 'he1bdpclc';
//		$creds['remember'] = true;
//		$user = wp_signon( $creds, false );
//		if ( is_wp_error($user) )
//		   echo $user->get_error_message();
//		
//		
//	}
//	echo 'Uri: '.selfURL();
//	foreach ($_SERVER as $key=>$value){
//		echo $key.':'.$value.'</br>';
//	}
//	echo "---<br/>";
//	foreach ($HTTP_SERVER_VARS as $key=>$value){
//		echo $key.':'.$value.'</br>';
//	}
	@session_start();
	$action=$_GET['action'];
	if ($action == 'requestSession'){
		$provider=$_GET['provider'];
		
		$api=getBachbillApi();
		
		$endUserAreaId=get_option('bachbill_endUserAreaId');
		$priceplanId=get_option('bachbill_priceplanId');
		$redirectUrl=selfURL().'?action=checkSession&redirect_to='.urldecode($_GET['redirect_to']);
		$ret=$api->getSession($priceplanId, $provider, $endUserAreaId, $redirectUrl);
		if ($api->hasErrors()){
			sendError($api->getErrorMessage());
			return;
		}
		$ret=$ret['GetSessionResponse'];
		wp_redirect($ret['redirectUrl']);
		$endUserSessionId=$ret['endUserSessionId'];
		$_SESSION['endUserSessionId']=$endUserSessionId;
		$_SESSION['provider']=$provider;
		return;
		
		
	}else if ($action == 'checkSession'){
		$provider=$_SESSION['provider'];
		$endUserAreaId=get_option('bachbill_endUserAreaId');
		$api=getBachbillApi();
		
		$priceplanId=get_option('bachbill_priceplanId');
		$endUserSessionId=$_SESSION['endUserSessionId'];
		
		$ret= $api->checkSession($priceplanId, $provider, $endUserAreaId, $endUserSessionId);
		if ($api->hasErrors()){
			sendError($api->getErrorMessage());
			return;
		}
		$ret=$ret['GetSessionResponse'];
		$endUserId=$ret['endUserId'];
		$endUserName=$ret['endUserName'];
		$status=$ret['status'];
		$endUserId='__user_'.substr($endUserId, strlen($endUserAreaId)+1);
//		unset($_SESSION['endUserSessionId']);
//		$_SESSION['logged_by_bachbill']=true;
		
		if ($endUserId){
//			social_signon($endUserId, $endUserName, '');
			add_filter('authenticate', 'override_login', 30, 3);
			
			$user_id = username_exists( $endUserId );
			if ( !$user_id ) {
				$random_password = wp_generate_password( 12, false );
				$user_id = wp_create_user( $endUserId, $random_password, $endUserId.'@invalidemail.com' );
//				$user_id = wp_create_user( $endUserId, $random_password);
//				$userdata=get_userdata($user_id);
				$index=strpos($endUserName, ' ');
				
				$firstName=$endUserName;
				$lastName='';
				if ($index>0){
					$firstName=substr($endUserName, 0, $index);
					$lastName=substr($endUserName, $index+1);
				}
				wp_update_user(array('ID' => $user_id,'display_name'=>$endUserName, 'first_name'=>$firstName, 'last_name'=>$lastName, 'description'=>__('User coming from', 'bachbill').' '.$provider));
			} else {
				$random_password = __('User already exists.  Password inherited.', 'bachbill');
			}
			
			
			$creds = array();
			$creds['user_login'] = $endUserId;
			//$creds['user_password'] = 'he1bdpclc';
			$creds['remember'] = true;
			$user = wp_signon( $creds, false );
			if ( is_wp_error($user) ){
			   echo $user->get_error_message();
			}
			
			$redirect=$_GET['redirect_to'];
			$redirect=$redirect?$redirect:getRootUrl();
			wp_redirect($redirect);
			return;
		}
		
	}
	get_header();
 ?>

<div id="content">

<?php 
	if (!$endUserId){
		echo __('There was an error during the login.', 'bachbill');
	}else {
		echo __('Login was successful', 'bachbill');
	}
?>

</div>

<?php 
//get_sidebar(); 
		
?>
<?php get_footer(); ?>