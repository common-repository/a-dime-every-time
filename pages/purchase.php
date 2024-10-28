<?php
	/*
	Template Name: Something
	*/
	//define('WP_USE_THEMES', true);
	require('../../../../wp-blog-header.php');
	require_once ('../api/bachbill_api.php');
	require_once('../functions.php');
	
	$currency=get_option('bachbill_currency');
	
	$api=getBachbillApi();
	
	$priceplanId=get_option('bachbill_priceplanId');
	$endUserAreaId=get_option('bachbill_endUserAreaId');
	$bundleId=$_GET['bundleId'];
	$pricepointId=$_GET['pricepointId'];
	$action=$_GET['action'];
	if ($action=='step1'){
		$promotionId=$_GET['promotionId'];
		$discount=$_GET['discount'];
		$promocode=$_GET['promocode'];
		$api->setParam('action', 'step1');
		if ($promotionId){
			$api->setParam('promotionId', $promotionId);
		}
		if ($promocode){
			$api->setParam('promocode', $promocode);
		}
		$promoStr='';
		if ($promotionId){
			$promoStr='&promotionId='.$promotionId;
			if ($promocode){
				$promoStr=$promoStr.'&promocode='.$promocode;
			}
		}
		
		if ($_POST['cctype']){
			// add credit card params
			$api->setParam('ccfirstname', $_POST['ccfirstname']);
			$api->setParam('cclastname', $_POST['cclastname']);
			$api->setParam('ccaddress', $_POST['ccaddress']);
			$api->setParam('cccity', $_POST['cccity']);
			$api->setParam('ccstate', $_POST['ccstate']);
			$api->setParam('cccountry_code', $_POST['cccountry_code']);
			$api->setParam('cczipcode', $_POST['cczipcode']);
			$api->setParam('cctype', $_POST['cctype']);
			$api->setParam('ccnumber', $_POST['ccnumber']);
			$api->setParam('ccexpdate_month', $_POST['ccexpdate_month']);
			$api->setParam('ccexpdate_year', $_POST['ccexpdate_year']);
			$api->setParam('cccvv2', $_POST['cccvv2']);
			$api->setParam('client_ip_addr', $_SERVER["REMOTE_ADDR"]);
		}else{
			$api->setParam('returnUrl', selfURL().'?action=step2&bundleId='.$bundleId.'&pricepointId='.$pricepointId.$promoStr.'&purchaseDetail='.$_GET['purchaseDetail']);
			$api->setParam('cancelUrl', selfURL().'?action=cancel');
		}
		$api->setParam('paymentDescription', $_GET['purchaseDetail']);
		 
		$res=$api->purchase($priceplanId, $endUserAreaId, getUserId(), $bundleId, $pricepointId);
		if ($api->hasErrors()){
			sendError($api->getErrorMessage());
			return;
		}
		$error='';
		$link='';
		if (!$res){
			$error=__('There was an error while trying to purchase the content', 'bachbill');
		}
		if ($res['error']){
			$error=$res['error']['message'];
		}else {
			$res=$res['PurchaseResponse'];
			if ($res['code']==0){
				$res=$res['paypal'];
				if (!$res){
//					$error=__('There was an error while trying to purchase the content', 'bachbill');
					// purchase cost zero, so redirect to the content
					@session_start();
					$redirect=$_SESSION['bachbill_redirect_after_purchase'];
					if (isset($redirect)){
						wp_redirect($redirect);
						return;
					}
				}else {
					if ($res['url']){
						wp_redirect($res['url']);
						return;
					}
					$transactionId=$res['transactionId'];
					$profileId=$res['profileId'];
					$profileStatus=$res['profileStatus'];
					if (!$transactionId && !($profileStatus=='ActiveProfile')){
						$error=__('There was an error while trying to purchase the content', 'bachbill');
					}else {
						@session_start();
						$redirect=$_SESSION['bachbill_redirect_after_purchase'];
						if (isset($redirect)){
							wp_redirect($redirect);
							return;
						}
					}
				}
			}else {
				$error=__('There was an error while trying to purchase the content', 'bachbill');
			}
		}
	}else if ($action=='step2'){
		$promotionId=$_GET['promotionId'];
		$promocode=$_GET['promocode'];
		$token=$_GET['token'];
		$PayerID=$_GET['PayerID'];
		$api->setParam('action', 'step2');
		if ($promotionId){
			$api->setParam('promotionId', $promotionId);
		}
		if ($promocode){
			$api->setParam('promocode', $promocode);
		}
		$api->setParam('token', $token);
		$api->setParam('PayerID', $PayerID);
		$api->setParam('paymentDescription', $_GET['purchaseDetail']);
		$res=$api->purchase($priceplanId, $endUserAreaId, getUserId(), $bundleId, $pricepointId);
		if ($api->hasErrors()){
			sendError($api->getErrorMessage());
			return;
		}
		$error='';
		if (!$res){
			$error=__('There was an error while trying to purchase the content', 'bachbill');
		}
		if ($res['error']){
			$error=$res['error']['message'];
		}else {
			$res=$res['PurchaseResponse'];
			if ($res['code']=='0'){
				$res=$res['paypal'];
				if (!$res){
					$error=__('There was an error while trying to purchase the content', 'bachbill');
				}else {
					$transactionId=$res['transactionId'];
					$profileId=$res['profileId'];
					$profileStatus=$res['profileStatus'];
					if (!$transactionId && !$profileStatus){
						$error=__('There was an error while trying to purchase the content', 'bachbill');
					}else {
						@session_start();
						$redirect=$_SESSION['bachbill_redirect_after_purchase'];
						if (isset($redirect)){
							wp_redirect($redirect);
							return;
						}
					}
				}
			}else {
				$error=__('There was an error while trying to purchase the content', 'bachbill');
			}
		}
	}else if ($action=='cancel'){
		@session_start();
		$error=__('The action was cancelled by the user', 'bachbill');
		$link=$_SESSION['bachbill_redirect_after_purchase'];
	}else if ($action=='render'){
	}else {
		$error=__('The action is not valid', 'bachbill');
	}
	
	
	//$redirect=$_SESSION['bachbill_redirect_after_purchase'];
//					if (isset($redirect)){
//						wp_redirect($redirect);
//					}
	get_header();
 ?><div id="content">
	<?php if ($error){
		echo $error;
		?>
		<br/>
		<a href="<?php echo ($link?$link:'javascript:history.back()');?>"><?php _e('Go back', 'bachbill');?></a>
		<?php 
		 
	}?>
	<?php

	 if ($action=='render'){
	 	$taxPercentage=get_option('bachbill_taxPercentage');
	 	$priceplanId=get_option('bachbill_priceplanId');
		$endUserAreaId=get_option('bachbill_endUserAreaId');
		$bundleId=$_GET['bundleId'];
		$pricepointId=$_GET['pricepointId'];
		$promotionId=$_GET['promotionId'];
		$discount=$_GET['discount'];
		$promocode=$_GET['promocode'];
		$purchaseUrl=selfURL().'?action=step1'.'&bundleId='.$bundleId.'&pricepointId='.$pricepointId.($promotionId?'&promotionId='.$promotionId:'').($discount?'&discount='.$discount:'').($promocode?'&promocode='.$promocode:'').'&purchaseDetail='.$_GET['purchaseDetail'];
		$price=$_GET['price'];
		$currency=$_GET['currency'];
		$dtAmt=0;
		@session_start();
		$contentUrl=$_SESSION['bachbill_redirect_after_purchase'];
		?>
		 <script>
		 	var enablePaypalButton=true;
		 	function validatePaymentForm(f){
		 	 	document.getElementById("confirmPayment").disabled=true;
		 	}
		 	function sendToPaypal(){
			 	if (!enablePaypalButton){
				 	return;
			 	}
		 	 	if (document.getElementById("confirmPayment")){
			 	 	document.getElementById("confirmPayment").disabled=true;
		 	 	}
		 		enablePaypalButton=false;
		 		document.location="<?php echo $purchaseUrl;?>"
		 	}
		 </script>
        <div style="padding: 20px">
		<h1><?php _e('Payment details', 'bachbill');?></h1>
		<table style="width: 400px" border="0" cellspacing="0" cellpadding="0"> 
        <tr> 
            <td> 
                <?php echo $_GET['purchaseDetail']?> 
            </td> 
            <td><?php echo $price?> <?php echo $currency=='EUR'?'&euro;':$currency?></td> 
        </tr> 
       <?php if ($discount) {
       	$dtAmt=round($price*$discount/100, 2);
       ?>
         <tr> 
            <td><?php _e('Discount', 'bachbill');?></td> 
            <td> 
                <?php echo $dtAmt?> <?php echo $currency=='EUR'?'&euro;':$currency?> 
            </td> 
        </tr> 
        <tr> 
            <td><?php _e('Subtotal', 'bachbill');?></td> 
            <td> 
                <?php echo $price-$dtAmt?> <?php echo $currency=='EUR'?'&euro;':$currency?> 
            </td> 
        </tr> 
        <?php }?>
        <tr> 
            <td><?php echo $taxPercentage?>% <?php _e('Taxes', 'bachbill');?></td> 
            <td> 
                <?php echo round(($price-$dtAmt)*$taxPercentage/100, 2)?> <?php echo $currency=='EUR'?'&euro;':$currency?> 
            </td> 
        </tr> 
        <tr> 
            <td><?php _e('Total', 'bachbill');?></td> 
            <td><?php echo round(($price-$dtAmt)*(1+$taxPercentage/100), 2)?> <?php echo $currency=='EUR'?'&euro;':$currency?><?php if ($_GET['recurring']=='true'){echo ' / '.$_GET['freqText'];}?></td> 
        </tr>
        </table>
        <?php
        $paymentMethods=get_option('bachbill_paymentMethods');
        $n=0;
        if ($paymentMethods['paypal']){
        $n++;
        ?>
        <table>
        	<?php if ($paymentMethods['creditCards']){
        		?>
         		<tr><td colspan="2"><b><?php _e('Option', 'bachbill');?> <?php echo $n;?>: <?php _e('Pay with Paypal', 'bachbill');?></b></td></tr>
        		<?php
        	}?>
         <tr> 
            <td align="right" style="vertical-align:middle"><a href="javascript:sendToPaypal()"><?php _e('Click to pay', 'bachbill');?></a></td> 
            <td align="left"><a href="javascript:sendToPaypal()"><img id="confirmPaymentPaypal" src="../img/horizontal_solution_PPeCheck.gif" style="margin-right:7px;"></a></td> 
        </tr>
         <tr> 
            <td colspan="2" align="center"><a href="javascript:history.back()"><?php _e('Go back', 'bachbill');?></a></td> 
        </tr>
        </table>
        <br/>
        <?php }
        if ($paymentMethods['creditCards']){
        $n++;
        ?> 
	        <form method="post" onSubmit="return validatePaymentForm(this)" action="<?php echo $purchaseUrl;?>">
	        <table>
	         <tr><td colspan="2"><b><?php _e('Option', 'bachbill');?> <?php echo $n;?>: <?php _e('Pay with credit card', 'bachbill');?></b></td>
	        <tr><td align="right"><?php _e('First Name', 'bachbill');?></td><td align="left"><input type="text" name="ccfirstname" size="50"/></td></tr>
	        <tr><td align="right"><?php _e('Last Name', 'bachbill');?></td><td align="left"><input type="text" name="cclastname" size="50"/></td></tr>
	        <tr><td align="right"><?php _e('Address', 'bachbill');?></td><td align="left"><input type="text" name="ccaddress" size="50"/></td></tr>
	        <tr><td align="right"><?php _e('City', 'bachbill');?></td><td align="left"><input type="text" name="cccity" size="50"/></td></tr>
	        <tr><td align="right"><?php _e('State', 'bachbill');?></td><td align="left"><input type="text" name="ccstate" size="50"/></td></tr>
	        <tr><td align="right"><?php _e('Country', 'bachbill');?></td>
	        <td align="left">
	        <select tabindex="80" id="cccountry_code" name="cccountry_code" class="" >
<option value="">-- <?php _e('Choose a Country', 'bachbill');?> --</option>
<?php 

$options = array(
array('value'=>'US','name'=>'United States'),
array('value'=>'AL','name'=>'Albania'),
array('value'=>'DZ','name'=>'Algeria'),
array('value'=>'AD','name'=>'Andorra'),
array('value'=>'AO','name'=>'Angola'),
array('value'=>'AI','name'=>'Anguilla'),
array('value'=>'AG','name'=>'Antigua and Barbuda'),
array('value'=>'AR','name'=>'Argentina'),
array('value'=>'AM','name'=>'Armenia'),
array('value'=>'AW','name'=>'Aruba'),
array('value'=>'AU','name'=>'Australia'),
array('value'=>'AT','name'=>'Austria'),
array('value'=>'AZ','name'=>'Azerbaijan Republic'),
array('value'=>'BS','name'=>'Bahamas'),
array('value'=>'BH','name'=>'Bahrain'),
array('value'=>'BB','name'=>'Barbados'),
array('value'=>'BE','name'=>'Belgium'),
array('value'=>'BZ','name'=>'Belize'),
array('value'=>'BJ','name'=>'Benin'),
array('value'=>'BM','name'=>'Bermuda'),
array('value'=>'BT','name'=>'Bhutan'),
array('value'=>'BO','name'=>'Bolivia'),
array('value'=>'BA','name'=>'Bosnia and Herzegovina'),
array('value'=>'BW','name'=>'Botswana'),
array('value'=>'BR','name'=>'Brazil'),
array('value'=>'VG','name'=>'British Virgin Islands'),
array('value'=>'BN','name'=>'Brunei'),
array('value'=>'BG','name'=>'Bulgaria'),
array('value'=>'BF','name'=>'Burkina Faso'),
array('value'=>'BI','name'=>'Burundi'),
array('value'=>'KH','name'=>'Cambodia'),
array('value'=>'CA','name'=>'Canada'),
array('value'=>'CV','name'=>'Cape Verde'),
array('value'=>'KY','name'=>'Cayman Islands'),
array('value'=>'TD','name'=>'Chad'),
array('value'=>'CL','name'=>'Chile'),
array('value'=>'C2','name'=>'China'),
array('value'=>'CO','name'=>'Colombia'),
array('value'=>'KM','name'=>'Comoros'),
array('value'=>'CK','name'=>'Cook Islands'),
array('value'=>'CR','name'=>'Costa Rica'),
array('value'=>'HR','name'=>'Croatia'),
array('value'=>'CY','name'=>'Cyprus'),
array('value'=>'CZ','name'=>'Czech Republic'),
array('value'=>'CD','name'=>'Democratic Republic of the Congo'),
array('value'=>'DK','name'=>'Denmark'),
array('value'=>'DJ','name'=>'Djibouti'),
array('value'=>'DM','name'=>'Dominica'),
array('value'=>'DO','name'=>'Dominican Republic'),
array('value'=>'EC','name'=>'Ecuador'),
array('value'=>'SV','name'=>'El Salvador'),
array('value'=>'ER','name'=>'Eritrea'),
array('value'=>'EE','name'=>'Estonia'),
array('value'=>'ET','name'=>'Ethiopia'),
array('value'=>'FK','name'=>'Falkland Islands'),
array('value'=>'FO','name'=>'Faroe Islands'),
array('value'=>'FM','name'=>'Federated States of Micronesia'),
array('value'=>'FJ','name'=>'Fiji'),
array('value'=>'FI','name'=>'Finland'),
array('value'=>'FR','name'=>'France'),
array('value'=>'GF','name'=>'French Guiana'),
array('value'=>'PF','name'=>'French Polynesia'),
array('value'=>'GA','name'=>'Gabon Republic'),
array('value'=>'GM','name'=>'Gambia'),
array('value'=>'DE','name'=>'Germany'),
array('value'=>'GI','name'=>'Gibraltar'),
array('value'=>'GR','name'=>'Greece'),
array('value'=>'GL','name'=>'Greenland'),
array('value'=>'GD','name'=>'Grenada'),
array('value'=>'GP','name'=>'Guadeloupe'),
array('value'=>'GT','name'=>'Guatemala'),
array('value'=>'GN','name'=>'Guinea'),
array('value'=>'GW','name'=>'Guinea Bissau'),
array('value'=>'GY','name'=>'Guyana'),
array('value'=>'HN','name'=>'Honduras'),
array('value'=>'HK','name'=>'Hong Kong'),
array('value'=>'HU','name'=>'Hungary'),
array('value'=>'IS','name'=>'Iceland'),
array('value'=>'IN','name'=>'India'),
array('value'=>'ID','name'=>'Indonesia'),
array('value'=>'IE','name'=>'Ireland'),
array('value'=>'IL','name'=>'Israel'),
array('value'=>'IT','name'=>'Italy'),
array('value'=>'JM','name'=>'Jamaica'),
array('value'=>'JP','name'=>'Japan'),
array('value'=>'JO','name'=>'Jordan'),
array('value'=>'KZ','name'=>'Kazakhstan'),
array('value'=>'KE','name'=>'Kenya'),
array('value'=>'KI','name'=>'Kiribati'),
array('value'=>'KW','name'=>'Kuwait'),
array('value'=>'KG','name'=>'Kyrgyzstan'),
array('value'=>'LA','name'=>'Laos'),
array('value'=>'LV','name'=>'Latvia'),
array('value'=>'LS','name'=>'Lesotho'),
array('value'=>'LI','name'=>'Liechtenstein'),
array('value'=>'LT','name'=>'Lithuania'),
array('value'=>'LU','name'=>'Luxembourg'),
array('value'=>'MG','name'=>'Madagascar'),
array('value'=>'MW','name'=>'Malawi'),
array('value'=>'MY','name'=>'Malaysia'),
array('value'=>'MV','name'=>'Maldives'),
array('value'=>'ML','name'=>'Mali'),
array('value'=>'MT','name'=>'Malta'),
array('value'=>'MH','name'=>'Marshall Islands'),
array('value'=>'MQ','name'=>'Martinique'),
array('value'=>'MR','name'=>'Mauritania'),
array('value'=>'MU','name'=>'Mauritius'),
array('value'=>'YT','name'=>'Mayotte'),
array('value'=>'MX','name'=>'Mexico'),
array('value'=>'MN','name'=>'Mongolia'),
array('value'=>'MS','name'=>'Montserrat'),
array('value'=>'MA','name'=>'Morocco'),
array('value'=>'MZ','name'=>'Mozambique'),
array('value'=>'NA','name'=>'Namibia'),
array('value'=>'NR','name'=>'Nauru'),
array('value'=>'NP','name'=>'Nepal'),
array('value'=>'NL','name'=>'Netherlands'),
array('value'=>'AN','name'=>'Netherlands Antilles'),
array('value'=>'NC','name'=>'New Caledonia'),
array('value'=>'NZ','name'=>'New Zealand'),
array('value'=>'NI','name'=>'Nicaragua'),
array('value'=>'NE','name'=>'Niger'),
array('value'=>'NU','name'=>'Niue'),
array('value'=>'NF','name'=>'Norfolk Island'),
array('value'=>'NO','name'=>'Norway'),
array('value'=>'OM','name'=>'Oman'),
array('value'=>'PW','name'=>'Palau'),
array('value'=>'PA','name'=>'Panama'),
array('value'=>'PG','name'=>'Papua New Guinea'),
array('value'=>'PE','name'=>'Peru'),
array('value'=>'PH','name'=>'Philippines'),
array('value'=>'PN','name'=>'Pitcairn Islands'),
array('value'=>'PL','name'=>'Poland'),
array('value'=>'PT','name'=>'Portugal'),
array('value'=>'QA','name'=>'Qatar'),
array('value'=>'CG','name'=>'Republic of the Congo'),
array('value'=>'RE','name'=>'Reunion'),
array('value'=>'RO','name'=>'Romania'),
array('value'=>'RU','name'=>'Russia'),
array('value'=>'RW','name'=>'Rwanda'),
array('value'=>'VC','name'=>'Saint Vincent and the Grenadines'),
array('value'=>'WS','name'=>'Samoa'),
array('value'=>'SM','name'=>'San Marino'),
array('value'=>'ST','name'=>'Sao Tome and Principe'),
array('value'=>'SA','name'=>'Saudi Arabia'),
array('value'=>'SN','name'=>'Senegal'),
array('value'=>'SC','name'=>'Seychelles'),
array('value'=>'SL','name'=>'Sierra Leone'),
array('value'=>'SG','name'=>'Singapore'),
array('value'=>'SK','name'=>'Slovakia'),
array('value'=>'SI','name'=>'Slovenia'),
array('value'=>'SB','name'=>'Solomon Islands'),
array('value'=>'SO','name'=>'Somalia'),
array('value'=>'ZA','name'=>'South Africa'),
array('value'=>'KR','name'=>'South Korea'),
array('value'=>'ES','name'=>'Spain'),
array('value'=>'LK','name'=>'Sri Lanka'),
array('value'=>'SH','name'=>'St. Helena'),
array('value'=>'KN','name'=>'St. Kitts and Nevis'),
array('value'=>'LC','name'=>'St. Lucia'),
array('value'=>'PM','name'=>'St. Pierre and Miquelon'),
array('value'=>'SR','name'=>'Suriname'),
array('value'=>'SJ','name'=>'Svalbard and Jan Mayen Islands'),
array('value'=>'SZ','name'=>'Swaziland'),
array('value'=>'SE','name'=>'Sweden'),
array('value'=>'CH','name'=>'Switzerland'),
array('value'=>'TW','name'=>'Taiwan'),
array('value'=>'TJ','name'=>'Tajikistan'),
array('value'=>'TZ','name'=>'Tanzania'),
array('value'=>'TH','name'=>'Thailand'),
array('value'=>'TG','name'=>'Togo'),
array('value'=>'TO','name'=>'Tonga'),
array('value'=>'TT','name'=>'Trinidad and Tobago'),
array('value'=>'TN','name'=>'Tunisia'),
array('value'=>'TR','name'=>'Turkey'),
array('value'=>'TM','name'=>'Turkmenistan'),
array('value'=>'TC','name'=>'Turks and Caicos Islands'),
array('value'=>'TV','name'=>'Tuvalu'),
array('value'=>'UG','name'=>'Uganda'),
array('value'=>'UA','name'=>'Ukraine'),
array('value'=>'AE','name'=>'United Arab Emirates'),
array('value'=>'GB','name'=>'United Kingdom'),
array('value'=>'UY','name'=>'Uruguay'),
array('value'=>'VU','name'=>'Vanuatu'),
array('value'=>'VA','name'=>'Vatican City State'),
array('value'=>'VE','name'=>'Venezuela'),
array('value'=>'VN','name'=>'Vietnam'),
array('value'=>'WF','name'=>'Wallis and Futuna Islands'),
array('value'=>'YE','name'=>'Yemen'),
array('value'=>'ZM','name'=>'Zambia'));

foreach ($options as $c)
{
    $selected = '';
    if ($_POST['country_code'] == $c['value'])
        $selected = 'selected="selected"';
    
    echo '<option value="'.$c['value'].'" '.$selected.'>'.$c['name'].'</option>';
}

?>
</select>
	        </td></tr>
	        <tr><td align="right"><?php _e('Zip code', 'bachbill');?></td><td align="left"><input type="text" name="cczipcode" size="50"/></td></tr>
	        <tr><td align="right"><?php _e('Credit card type', 'bachbill');?></td>
	        <td align="left">
		        <select name="cctype">
			        <option value="Visa"><?php _e('Visa', 'bachbill');?></option>
					<option value="MasterCard"><?php _e('MasterCard', 'bachbill');?></option>
					<option value="Discover"><?php _e('Discover', 'bachbill');?></option>
					<option value="Amex"><?php _e('Amex', 'bachbill');?></option>
					<option value="Maestro"><?php _e('Maestro', 'bachbill');?></option>
					<option value="Solo"><?php _e('Solo', 'bachbill');?></option>
				</select>
			</td></tr>
	        <tr><td align="right"><?php _e('Credit card number', 'bachbill');?></td><td align="left"><input type="text" name="ccnumber" size="20" maxlength="16"/></td></tr>
			<tr><td align="right"><?php _e('Expiry date', 'bachbill');?></td>
	        <td align="left">
				<select tabindex="45" id="ccexpdate_month" name="ccexpdate_month">
					<option >01</option><option >02</option><option >03</option><option >04</option><option >05</option><option >06</option><option >07</option><option >08</option><option >09</option><option >10</option><option >11</option><option >12</option></select> &nbsp;
				<select tabindex="50" id="ccexpdate_year" name="ccexpdate_year">
					<option >2011</option><option >2012</option><option >2013</option><option >2014</option><option >2015</option><option >2016</option><option >2017</option><option >2018</option><option >2019</option><option >2020</option><option >2021</option><option >2022</option><option >2023</option><option >2024</option><option >2025</option><option >2026</option><option >2027</option><option >2028</option><option >2029</option><option >2030</option>
				</select>
			</td></tr>
	        <tr><td align="right"><?php _e('Security code', 'bachbill');?></td><td align="left"><input type="text" name="cccvv2" size="4" maxlength="4"/></td></tr>
	        <tr><td><a href="<?php echo $contentUrl;?>"><?php _e('Go back', 'bachbill');?></a></td><td align="right"><input id="confirmPayment" type="submit" value="<?php _e('Confirm Payment', 'bachbill');?>"/></td>
	        <?php }?>
	    	</table> 
	    	</form>
	    	<script>
		 	 	document.getElementById("confirmPayment").disabled=false;
		 	 	document.getElementById("confirmPaymentPaypal").disabled=false;
		 	</script>
		<?php
		
	} 
	?>
	        </div><br/><br/><br/><br/><br/><br/><br/><br/><br/>
	
</div>

<?php 
//get_sidebar(); 
		
?>
<?php get_footer(); ?>