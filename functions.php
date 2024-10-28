<?php 
require_once ('api/bachbill_api.php');
  
global $API_URL;
$API_URL='https://api.bachbill.com';
global $SOCIAL_NETWORK_NAMES;
$SOCIAL_NETWORK_NAMES=array("facebook"=>"Facebook", "google"=>"Google", "twitter"=>"Twitter", "linkedin"=>"Linkedin", "yahoo"=>"Yahoo");
$SOCIAL_NETWORK_ICONS=array("facebook"=>"facebook.png", "google"=>"google.png", "twitter"=>"twitter.png", "linkedin"=>"linkedin.png", "yahoo"=>"yahoo.png");
function selfURL() {
		$s = empty($_SERVER['HTTPS']) ? '' : ($_SERVER['HTTPS'] == 'on') ? 's' : '';
		$protocol = 'http'.$s;
		$port = ($_SERVER['SERVER_PORT'] == '80') ? '' : (':'.$_SERVER['SERVER_PORT']);
		$uri=$_SERVER['REQUEST_URI'];
		return $protocol.'://'.$_SERVER['SERVER_NAME'].$port.substr($uri, 0, strpos($uri, '?'));
}
function getRootUrl($ssl=false){
	$url=get_bloginfo('url');
	if ($ssl && strpos($url, 'http://')===0){
		return 'https://'.substr($url, 7);
	}
	return $url;
}

class service {
  public $creationDate; // dateTime
  public $descriptionText; // string
  public $extendedAttributes; // extendedAttribute
  public $id; // string
  public $invoiceText; // string
  public $modificationDate; // dateTime
  public $permanentRights; // boolean
  public $pricepoints; // pricepoint
  public $services; // service
  public $status; // string
  public $stock; // long
}

class bundle {
  public $creationDate; // dateTime
  public $descriptionText; // string
  public $extendedAttributes; // extendedAttribute
  public $id; // string
  public $invoiceText; // string
  public $modificationDate; // dateTime
  public $permanentRights; // boolean
  public $pricepoints; // pricepoint
  public $services; // service
  public $status; // string
  public $stock; // long
}


class pricepoint {
  public $accessDuration; // int
  public $accessDurationUnit; // string
  public $creationDate; // dateTime
  public $currency; // string
  public $descriptionText; // string
  public $expireDuration; // int
  public $expireDurationUnit; // string
  public $extendedAttributes; // extendedAttribute
  public $id; // string
  public $modificationDate; // dateTime
  public $price; // double
  public $promotions; // promotion
  public $recurring; // boolean
  public $status; // string
}

function getBachbillApi(){
	$adminAreaId=get_option('bachbill_adminAreaId');
	$adminUserId=get_option('bachbill_adminUserId');
	$adminUserPassword=get_option('bachbill_adminUserPassword');
	global $API_URL;
	$api=new BachbillApi($API_URL);
	$api->setAdminAreaId($adminAreaId);
	$api->setAdminUserId($adminUserId);
	$api->setAdminUserPassword($adminUserPassword);
	return $api;
}
function getUserId(){
	$current_user=wp_get_current_user();
	$endUserId=$current_user->user_login;
	$pos=strpos($endUserId, '__user_');
	if ($pos===0){
		$endUserId=substr($endUserId, 7);
	}
	return $endUserId;
}
function renderPromotion($pricepoint, $pr, $purchaseUrl){
	$res='';
	$res=$res.'<tr>';
	$finalPrice=$pricepoint['price']*(100-$pr['discount'])/100;
	$finalPrice=$finalPrice==0?__('FREE', 'bachbill'):''.$finalPrice.' '.$pricepoint['currency'];
	$res=$res.'<td><span style="font-size: 12pt">'.__('Final price:', 'bachbill').' '.$finalPrice.'</span><br/><br/>';
	$res=$res.$pr['name'].'. '.$pr['discount'].' % '.__('discount', 'bachbill').'. '.$pr['descriptionText'].'. ';
	$promoUrl=$purchaseUrl.'&promotionId='.$pr['id'];
	
	if ($pr['hasPromocode']=='true'){
		$res=$res.'<br/>'.__('You need a promocode', 'bachbill').': <input type="text" id="'.$pr['id'].$pricepoint['id'].'" name="promocode"/> ';
	}
	$res=$res.'<input type="button" value="'.__('Buy with promotion', 'bachbill').'" onClick="document.location=\''.$promoUrl.'\'+\'&discount='.$pr['discount'].($pr['hasPromocode']?'&promocode=\'+document.getElementById(\''.$pr['id'].$pricepoint['id'].'\').value':'\'').'"/></td>';
	
	$res=$res.'</tr>';
	return $res;
}
function renderPricepoint($bundleDesc, $endUserAreaId, $budleId, $p){
	$res='';
	$accessDuration=$p['accessDuration'];
	$expireDuration=$p['expireDuration'];
	$hasAccesses=$accessDuration>0;
	$hasExpiry=$expireDuration>0;
	$accessDuration=$p['accessDuration'];
	$expireDuration=$p['expireDuration'];
	$accessDurationUnit=$p['accessDurationUnit'];
	$expireDurationUnit=$p['expireDurationUnit'];
	$recurring=$p['recurring']=='true';
	$price=$p['price'];
	$currency=$p['currency'];
	
	
	
	if ($hasAccesses && $hasExpiry){
		$res=$res.$accessDuration.' '.__('article', 'bachbill').($accessDuration>1?'s':'').' in '.$expireDuration.' '.__($expireDurationUnit, 'bachbill').($expireDuration>1?'s':'');
	}else if ($hasAccesses){
		$res=$res.$accessDuration.' '.__('article', 'bachbill').($accessDuration>1?'s':'');
	}else if ($hasExpiry){
		$res=$res.$expireDuration.' '.__($expireDurationUnit, 'bachbill').($expireDuration>1?'s':'');
	}else {
		$res=$res.__('No match...', 'bachbill');
	}
	$recStr=$recurring?' for '.$price.' '.$currency.' '.__('per', 'bachbill').' '.__($expireDurationUnit, 'bachbill'):'';
	$purchaseDetail=$bundleDesc.' ('.$res.$recStr.')';
	$res=$res.' '.__('for', 'bachbill').' '.$price.' '.$currency;
	if ($recurring){
		$res=$res.', '.__('recurring', 'bachbill').'';
	}
	$useSSL=get_option('bachbill_useSSL');
	$freqText='';
	if ($recurring){
		if ($expireDuration==1){
			$freqText=__($expireDurationUnit, 'bachbill');
		}else if ($expireDuration>1){
			$freqText=''.$expireDuration.' '.__($expireDurationUnit, 'bachbill').'s';
		}
	}
	$purchaseUrl=getRootUrl($useSSL).'/wp-content/plugins/a-dime-every-time/pages/purchase.php?action=render&recurring='.($recurring?'true':'false').'&purchaseDetail='.$purchaseDetail.'&price='.$price.'&currency='.$currency.'&bundleId='.$budleId.'&pricepointId='.$p['id'].'&endUserId='.$endUserAreaId.'/'.getUserId().'&freqText='.$freqText;
	
	$res=$res.' <a href="'.$purchaseUrl.'"><img src="'.getRootUrl().'/wp-content/plugins/a-dime-every-time/img/ok.png" style="vertical-align: middle" width="30"/></a> <a href="'.$purchaseUrl.'">'.__('Buy', 'bachbill').'</a>';
	
	$promotions=$p['promotions'];
	if ($promotions){
		if ($promotions['Promotion'] && !$promotions['Promotion']['id']){
			echo __('yes', 'bachbill').'<br/>';
			$promotions=$promotions['Promotion'];
		}
		$res=$res.'<table style="background-color: #ccffcc; font-size: 12px; padding: 5px">';
		$res=$res.'<tr>';
		$res=$res.'<td><img src="'.getRootUrl().'/wp-content/plugins/a-dime-every-time/img/candy.gif" style="vertical-align: middle" width="30"/><b>'.__('This option has a promotion', 'bachbill').'</b></td>';
		$res=$res.'</tr>';
		foreach ($promotions as $pr){
			$res=$res.renderPromotion($p, $pr, $purchaseUrl);
		}
		$res=$res.'</table>';
		
	}
	return $res;
}
function renderPurchaseExperience($bundles){
	$endUserAreaId=get_option('bachbill_endUserAreaId');
	$res='';
	$res=$res.'<br/><div style="padding: 20px; background-color: rgba(255,255,255,0.4)"><h2>'.__('To see more you need to subscribe to this content', 'bachbill').':</h2><br/><table style="border: 0px">';
	if ($bundles['Bundle'] && !$bundles['Bundle']['id']){
		$bundles=$bundles['Bundle'];
	}
	foreach ($bundles as $b){
		$bundleId=$b['id'];
		$id=substr($bundleId, 2);
		$isService=false;
		$icon='puzzle_bundle2.png';
		$category=null;
		$post=null;
		if (strpos($bundleId, "s_")===0){
			$icon='puzzle.png';
			$isService=true;
			$post=get_post($id);
			$bundleDesc=$post->post_title;
		}else {
			$category=get_category($id);
			if ($category){
				$bundleDesc=$category->name;
			}else {
				//!!! could be a good point where to delete the bundle?
			}
		}
		$pricepoints=$b['pricepoints'];
		if ($pricepoints['Pricepoint'] && !$pricepoints['Pricepoint']['id']){
			$pricepoints=$pricepoints['Pricepoint'];
		}
		if ($pricepoints && ($category || $post)){
			$res=$res.'<tr><td style="border: 0px" colspan="2"><img style="vertical-align: middle" width="30" src="'.getRootUrl().'/wp-content/plugins/a-dime-every-time/img/'.$icon.'"/> <b>'.$bundleDesc.'</b>:</td></tr>';
			foreach ($pricepoints as $p){
				$res=$res.'<tr>';
				$res=$res.'<td style="border: 0px"></td><td style="border: 0px">'.renderPricepoint($bundleDesc, $endUserAreaId, $bundleId, $p).'</td>';
				$res=$res.'</tr>';
			}
		}
	}
	$res=$res.'</table></div><br/>';
	return $res;
}
function renderSubscriptions($subscriptions){
	$res='<div id="subscriptions" style="padding: 20px; background-color: rgba(255,255,255,0.4)">';
	$res=$res.'<br/><table style="boder: 1px solid #cccccc; font-size: 12px">
		<tr style="font-weight: bold">
			<td style="padding: 10px">id</td><td style="padding: 10px">'.__('Description', 'bachbill').'</td><td style="padding: 10px">'.__('Price', 'bachbill').'(*)</td><td style="padding: 10px">'.__('Discount', 'bachbill').'</td><td style="padding: 10px">'.__('Expiry', 'bachbill').'</td><td style="padding: 10px">'.__('Status', 'bachbill').'</td><td style="padding: 10px">'.__('Date', 'bachbill').'</td><td style="padding: 10px"></td>
		</tr>';
	if ($subscriptions){
		foreach ($subscriptions as $s){
			$b=$s['bundle'];
			$p=$s['pricepoint'];
			$id=substr($b['id'], 2);
			$descriptionText=strpos($b['id'], 's_')===0?get_post($id)->post_title:get_category($id)->cat_name;
			$res=$res.'<tr><td style="padding: 10px">'.$s['id'].'</td><td style="padding: 10px">'.
								$descriptionText.'</td><td style="padding: 10px">'.($p['price']*(1-$s['discount']/100)).' '.$p['currency'].'</td><td style="padding: 10px">'.($s['discount']>0?$s['discount'].'%':'').'</td>'.
								'<td style="padding: 10px">'.($s['expiryDate']?$s['expiryDate']:'').($s['pendingAccesses']>=0?(', '?$s['expiryDate']:'').$s['pendingAccesses'].' accesses':'').($p['recurring']=='true'?' (recurring)':'').'</td>'.
								'<td style="padding: 10px">'.($s['cancelled']?'cancelled':$s['subscriptionStatus']).'</td>'.
								'<td style="padding: 10px">'.$s['creationDate'].'</td>'.
								'<td style="padding: 10px">'.($p['recurring']=='true' && !$s['cancelled']?'<a href="javascript:cancelSubscription(\'selfcare.php?action=cancel&subscriptionId='.$s['id'].'\')">'.__('Cancel', 'bachbill').'</a>':'').'</td></tr>';
		}
	}else {
		$res=$res.'<tr><td colspan="6">'.__('No active subscriptions', 'bachbill').'</td></tr>';
	}
	$res=$res.'</table>';
	$res=$res.'<br/><input id="activeOnly" '.($_GET['activeOnly'] && $_GET['activeOnly']=='false'?'checked="checked"':'').' type="checkbox" onClick="document.location=\'selfcare.php?action=list&activeOnly=\'+(!document.getElementById(\'activeOnly\').checked)"/> '.__('See also non active ones', 'bachbill');
	$res=$res.'<br/><br/>(*) '.get_option('bachbill_taxPercentage').'% '.__('Taxes not included', 'bachbill');
	$res=$res.'</div>';
	return $res;
}

function renderTransactions($transactions){
	$res='<div id="transactions" style="padding: 20px; background-color: rgba(255,255,255,0.4)">';
	$res=$res.'<br/><table style="boder: 1px solid #cccccc; font-size: 12px">
		<tr style="font-weight: bold">
			<td style="padding: 10px">id</td>
		</tr>';
	if ($transactions){
		foreach ($transactions as $t){
			$post_id=substr($t['serviceId'], 2);
			$post=get_post($post_id);

			$res=$res.'<tr><td style="padding: 10px"><a href='.getRootUrl().'?p='.$post_id.'>'.$post->post_title.'</a></td><td style="padding: 10px"></td>';
		}
	}else {
		$res=$res.'<tr><td colspan="6">'.__('No transactions', 'bachbill').'</td></tr>';
	}
	$res=$res.'</table>';
	$res=$res.'</div>';
	return $res;
}

function createRandomJob($manager, $adminAreaId, $adminUserId, $priceplanId){
	$j=new job();
	$j->id=0;
	$j->name='wp_'.rand(0, 10000);
	$j->description='Wordpress service provision';
	$j->createdBy=$adminAreaId.'/'.$adminUserId;
	$j->coWorkers=array();
	$sj=new startJob();
	$sj->job=$j;
	$sj->priceplanId=$priceplanId;
	$res=$manager->startJob($sj);
	return $res->return->object;
}
function deleteJob($manager, $priceplanId, $job){
	$dj=new deleteJob();
	$dj->job=$job;
	$dj->priceplanId=$priceplanId;
	$res=$manager->deleteJob($dj);
	return $res;
}
function provisionService($post_id){
	$post=get_post($post_id);
	$s=new Service();
	$s->id='s_'.$post_id;
	$s->descriptionText=$post->post_title;
	$s->permanentRights=true;
	$s->services=array();
	$s->pricepoints=array();
	$s->extendedAttributes=array();
	return $s;
}
function provisionBundle($term_id){
	$catName=get_cat_name($term_id);
	$b=new Bundle();
	$b->id='b_'.$term_id;
	$b->descriptionText=$catName;
	$b->services=array();
	$b->pricepoints=array();
	$b->extendedAttributes=array();

	return $b;
}

function editPostErrorHandler( $errno, $errstr, $errfile, $errline, $errcontext){
  $message=__('There was an error processing the request', 'bachbill');
  wp_redirect($_SERVER['HTTP_REFERER'].'&bErrorStr='.urlencode($message));
}
function saveBundle($bundle, $managePricepoints=true){
	$chunk_size=500;
	$priceplanId=get_option('bachbill_priceplanId');
	$chunked=false;
	$services=array();
	if (sizeof($bundle->services)>$chunk_size){
		//exec( 'echo "big array">>/tmp/php.log');
		$chunked=true;
		$services=$bundle->services;
		$bundle->services=array();
		for ($i=0; $i<$chunk_size; $i++){
			array_push($bundle->services, $services[$i]);
		}
		//exec( 'echo "first chunk of '.sizeof($bundle->services).'">>/tmp/php.log');
	}
	$b=json_encode($bundle);
	$api=getBachbillApi();
	$api->setParam('id', $bundle->id);
	$api->setParam('obj', 'bundle');
	$api->setParam('action', 'edit');
	$api->setParam('managePricepoints', $managePricepoints?'true':'false');
	$api->setParam('bundle', $b);
	$res=$api->callOnIncomingUrlAction($priceplanId, '/quick_provision');
	if ($api->getErrorCode()<>0){
		//exec( 'echo "error '.$api->getErrorCode().'">>/tmp/php.log');
		wp_redirect($_SERVER['HTTP_REFERER'].'&bErrorStr='.urlencode($api->getErrorMessage()));
		return false;
	}
	if ($chunked){
		$bundle->services=array();
		for ($i=$chunk_size; $i<sizeof($services); $i++){
			array_push($bundle->services, $services[$i]);
			//exec( 'echo "pushing service '.$services[$i]->id.'">>/tmp/php.log');
			if (($i>$chunk_size && $i%$chunk_size==0) || $i==sizeof($services)-1){
				// another chunk
				//exec( 'echo "new chunk from '.$i.'">>/tmp/php.log');
				$b=json_encode($bundle);
				$api=getBachbillApi();
				$api->setParam('id', $bundle->id);
				$api->setParam('obj', 'bundle');
				$api->setParam('action', 'addServices');
				$api->setParam('bundle', $b);
				$res=$api->callOnIncomingUrlAction($priceplanId, '/quick_provision');
				if ($api->getErrorCode()<>0){
					//exec( 'echo "error 2">>/tmp/php.log');
					wp_redirect($_SERVER['HTTP_REFERER'].'&bErrorStr='.urlencode($api->getErrorMessage()));
					return false;
				}
				$bundle->services=array();
			}
		}
	}
	return true;
}
function deleteBundle($id){
	$priceplanId=get_option('bachbill_priceplanId');
	$api=getBachbillApi();
	$api->setParam('id', $id);
	$api->setParam('obj', 'bundle');
	$api->setParam('action', 'delete');
	$res=$api->callOnIncomingUrlAction($priceplanId, '/quick_provision');
	if ($api->getErrorCode()<>0){
		wp_redirect($_SERVER['HTTP_REFERER'].'&bErrorStr='.urlencode($api->getErrorMessage()));
		return false;
	}
	return true;
}



function provisionPricepoint($pricepointId, $price, $currency, $accessDuration, $accessDurationUnit, $expireDuration, $expireDurationUnit, $recurring, $descriptionText){
	$p=new Pricepoint();
	$p->id=$pricepointId;
	$p->price=$price;
	$p->currency=$currency;
	$p->accessDuration=$accessDuration;
	if ($accessDurationUnit) $p->accessDurationUnit=$accessDurationUnit;
	$p->expireDuration=$expireDuration;
	if ($expireDurationUnit) $p->expireDurationUnit=$expireDurationUnit;
	if ($recurring) $p->recurring=$recurring;
	$p->descriptionText=$descriptionText;
	$p->extendedAttributes=array();
	
	return $p;
}

function addEditServiceBundles($serviceId, $description, $addedBundleIds, $deletedBundleIds){
	$priceplanId=get_option('bachbill_priceplanId');
	$api=getBachbillApi();
	$api->setParam('id', $serviceId);
	$api->setParam('obj', 'service');
	$api->setParam('action', 'addEditServiceBundles');
	$api->setParam('description', $description);
	$added='';
	$deleted='';
	foreach ($addedBundleIds as $b){
		if ($added!=''){
			$added.=',';
		}
		$added.=$b;
	}
	foreach ($deletedBundleIds as $b){
		if ($deleted!=''){
			$deleted.=',';
		}
		$deleted.=$b;
	}
	$api->setParam('addedBundleIds', $added);
	$api->setParam('deletedBundleIds', $deleted);
	$res=$api->callOnIncomingUrlAction($priceplanId, '/quick_provision');
	if ($api->getErrorCode()<>0){
		wp_redirect($_SERVER['HTTP_REFERER'].'&bErrorStr='.urlencode($api->getErrorMessage()));
		return false;
	}
	return true;
}

function printPostPricepoints($pricepoints){
	
}
function sendError($msg){
	// format message first
	$msg=str_ireplace('ppal_direct_payment', 'ADimeEveryTime', $msg);
	$msg=str_ireplace('Security header is not valid', __('Security header is not valid. Please configure the ADimeEveryTime plugin', 'bachbill'), $msg);
	wp_redirect(getRootUrl().'/wp-content/plugins/a-dime-every-time/pages/message.php?message='.urlencode($msg));
}
function redirectToError($msg){
	?><script>document.location.href="<?php echo getRootUrl().'/wp-content/plugins/a-dime-every-time/pages/message.php?message='.urlencode($msg);?>";</script><?php 
}
function renderAdminError($message){
	$message=str_replace('partner', 'merchant', $message);
	?>
	<br/><br/><br/><?php echo $message;?> <input type="button" name="" class="button-secondary action" value="<?php _e('Go back', 'bachbill');?>" onClick="history.back()">
<?php 
}

function showErrorWithAlert($errorMsg){
	?>
	<script>alert("<?php echo $errorMsg;?>");</script>
	<span style="color: red; font-size: 18pt"><?php echo $errorMsg;?><br/><br/></span>
	<?php
}
function getArrayAsString($arr){
	$res='';
	if ($arr){
		$start=true;
		foreach ($arr as $str){
			if ($start){
				$start=false;
			}else{
				$res.=',';
			}
			$res.=$str;
		}
	}
	return $res;
}

global $MIME_TYPES;
$MIME_TYPES=array( "ez" => "application/andrew-inset", 
         "hqx" => "application/mac-binhex40", 
         "cpt" => "application/mac-compactpro", 
         "doc" => "application/msword", 
         "bin" => "application/octet-stream", 
         "dms" => "application/octet-stream", 
         "lha" => "application/octet-stream", 
         "lzh" => "application/octet-stream", 
         "exe" => "application/octet-stream", 
         "class" => "application/octet-stream", 
         "so" => "application/octet-stream", 
         "dll" => "application/octet-stream", 
         "oda" => "application/oda", 
         "pdf" => "application/pdf", 
         "ai" => "application/postscript", 
         "eps" => "application/postscript", 
         "ps" => "application/postscript", 
         "smi" => "application/smil", 
         "smil" => "application/smil", 
         "wbxml" => "application/vnd.wap.wbxml", 
         "wmlc" => "application/vnd.wap.wmlc", 
         "wmlsc" => "application/vnd.wap.wmlscriptc", 
         "bcpio" => "application/x-bcpio", 
         "vcd" => "application/x-cdlink", 
         "pgn" => "application/x-chess-pgn", 
         "cpio" => "application/x-cpio", 
         "csh" => "application/x-csh", 
         "dcr" => "application/x-director", 
         "dir" => "application/x-director", 
         "dxr" => "application/x-director", 
         "dvi" => "application/x-dvi", 
         "spl" => "application/x-futuresplash", 
         "gtar" => "application/x-gtar", 
         "hdf" => "application/x-hdf", 
         "js" => "application/x-javascript", 
         "skp" => "application/x-koan", 
         "skd" => "application/x-koan", 
         "skt" => "application/x-koan", 
         "skm" => "application/x-koan", 
         "latex" => "application/x-latex", 
         "nc" => "application/x-netcdf", 
         "cdf" => "application/x-netcdf", 
         "sh" => "application/x-sh", 
         "shar" => "application/x-shar", 
         "swf" => "application/x-shockwave-flash", 
         "sit" => "application/x-stuffit", 
         "sv4cpio" => "application/x-sv4cpio", 
         "sv4crc" => "application/x-sv4crc", 
         "tar" => "application/x-tar", 
         "tcl" => "application/x-tcl", 
         "tex" => "application/x-tex", 
         "texinfo" => "application/x-texinfo", 
         "texi" => "application/x-texinfo", 
         "t" => "application/x-troff", 
         "tr" => "application/x-troff", 
         "roff" => "application/x-troff", 
         "man" => "application/x-troff-man", 
         "me" => "application/x-troff-me", 
         "ms" => "application/x-troff-ms", 
         "ustar" => "application/x-ustar", 
         "src" => "application/x-wais-source", 
         "xhtml" => "application/xhtml+xml", 
         "xht" => "application/xhtml+xml", 
         "zip" => "application/zip", 
         "au" => "audio/basic", 
         "snd" => "audio/basic", 
         "mid" => "audio/midi", 
         "midi" => "audio/midi", 
         "kar" => "audio/midi", 
         "mpga" => "audio/mpeg", 
         "mp2" => "audio/mpeg", 
         "mp3" => "audio/mpeg", 
         "aif" => "audio/x-aiff", 
         "aiff" => "audio/x-aiff", 
         "aifc" => "audio/x-aiff", 
         "m3u" => "audio/x-mpegurl", 
         "ram" => "audio/x-pn-realaudio", 
         "rm" => "audio/x-pn-realaudio", 
         "rpm" => "audio/x-pn-realaudio-plugin", 
         "ra" => "audio/x-realaudio", 
         "wav" => "audio/x-wav", 
         "pdb" => "chemical/x-pdb", 
         "xyz" => "chemical/x-xyz", 
         "bmp" => "image/bmp", 
         "gif" => "image/gif", 
         "ief" => "image/ief", 
         "jpeg" => "image/jpeg", 
         "jpg" => "image/jpeg", 
         "jpe" => "image/jpeg", 
         "png" => "image/png", 
         "tiff" => "image/tiff", 
         "tif" => "image/tif", 
         "djvu" => "image/vnd.djvu", 
         "djv" => "image/vnd.djvu", 
         "wbmp" => "image/vnd.wap.wbmp", 
         "ras" => "image/x-cmu-raster", 
         "pnm" => "image/x-portable-anymap", 
         "pbm" => "image/x-portable-bitmap", 
         "pgm" => "image/x-portable-graymap", 
         "ppm" => "image/x-portable-pixmap", 
         "rgb" => "image/x-rgb", 
         "xbm" => "image/x-xbitmap", 
         "xpm" => "image/x-xpixmap", 
         "xwd" => "image/x-windowdump", 
         "igs" => "model/iges", 
         "iges" => "model/iges", 
         "msh" => "model/mesh", 
         "mesh" => "model/mesh", 
         "silo" => "model/mesh", 
         "wrl" => "model/vrml", 
         "vrml" => "model/vrml", 
         "css" => "text/css", 
         "html" => "text/html", 
         "htm" => "text/html", 
         "asc" => "text/plain", 
         "txt" => "text/plain", 
         "rtx" => "text/richtext", 
         "rtf" => "text/rtf", 
         "sgml" => "text/sgml", 
         "sgm" => "text/sgml", 
         "tsv" => "text/tab-seperated-values", 
         "wml" => "text/vnd.wap.wml", 
         "wmls" => "text/vnd.wap.wmlscript", 
         "etx" => "text/x-setext", 
         "xml" => "text/xml", 
         "xsl" => "text/xml", 
         "mpeg" => "video/mpeg", 
         "mpg" => "video/mpeg", 
         "mpe" => "video/mpeg", 
         "qt" => "video/quicktime", 
         "mov" => "video/quicktime", 
         "mxu" => "video/vnd.mpegurl", 
         "avi" => "video/x-msvideo", 
         "movie" => "video/x-sgi-movie", 
         "ice" => "x-conference-xcooltalk" 
      ); 
function getMimeType($fileName){
	$index=strrpos($fileName, '.');
	$ext='';
	if ($index>0){
		$ext=substr($fileName, $index+1);
	}else {
		return '';
	}
	global $MIME_TYPES;
	return $MIME_TYPES[$ext];
}

?>