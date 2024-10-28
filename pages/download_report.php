<?php

require_once('../api/bachbill_api.php');
require('../../../../wp-load.php');
require_once('../functions.php');
$api=getBachbillApi();
	$priceplanId=get_option('bachbill_priceplanId');
	$content=$api->getReport($priceplanId, $_POST['reportName'], $_POST['date1'], $_POST['date2']);
	if ($api->hasErrors()){
		sendError($api->getErrorMessage());
		return;
	}
//	header('Content-Description: File Transfer');
	header('Content-Type: application/pdf');
//	header('Content-Length: ' . filesize(1572));
	
	// to open in browser
	header('Content-Disposition: attachment; filename=report.pdf');
	echo $content;
	// to download
	// header('Content-Disposition: attachment; filename=' . basename($file));?>