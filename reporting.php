<?php


function bachbill_reporting() {
		?>
		<link rel="stylesheet" type="text/css" href="../wp-content/plugins/a-dime-every-time/css/datePicker.css"/>
		<script type="text/javascript" src="../wp-content/plugins/a-dime-every-time/js/datePicker.js"></script>

<script type="text/javascript">
	function validateReportForm(f){
		if (!f.date1.value){
			alert("<?php _e('Please select a from date', 'bachbill');?>");
			return false;
		}
		if (!f.date2.value){
			alert("<?php _e('Please select a to date', 'bachbill');?>");
			return false;
		}
		if (!document.getElementById("c1").checked && !document.getElementById("c2").checked && !document.getElementById("c3").checked){
			alert("<?php _e('Please select report type', 'bachbill');?>");
			return false;
		}
		return true;
	}

	
</script>

<form id="fff" onSubmit="return validateReportForm(this);" target="_blank" name="report" method="post" action="../wp-content/plugins/a-dime-every-time/pages/download_report.php">
<div style="margin:2em; padding: 2em; background-color: #EEE; border-radius: 1em; ">
	<div style="background-color: #DDD; border-top-left-radius: 1em; border-top-right-radius: 1em; padding:1em; border-bottom:1px solid #eee;">
		<table>
			<tr>
				<td><img style="vertical-align: middle;" width="130" src="../wp-content/plugins/a-dime-every-time/img/dime_logo.png"/></td>
				<td>
					<div style="padding: 0.5em; padding-left: 2em; color: #666; font-size: 150%; line-height: 150%;">
						<h1><?php _e('Activity reports', 'bachbill');?></h1><br/><br/> 
					</div>
				</td>
			</tr>
		</table>
	<div style="clear: both;"></div>
	<div id="account_info2" style="padding: 20px; background-color: #EEE; border-radius: 1em; box-shadow: #888 6px 6px 15px;">
		<h3><?php _e('Please select the type of report', 'bachbill');?>:</h3>
		<img width="50" style="vertical-align: middle" src="../wp-content/plugins/a-dime-every-time/img/Gear.png"/> <input type="radio" id="c1" name="reportName" value="serviceUsage"/> <?php _e('Service Usage', 'bachbill');?><br/><br/>
		<img width="50" style="vertical-align: middle" src="../wp-content/plugins/a-dime-every-time/img/revenue-share.png"/> <input type="radio" id="c2" name="reportName" value="payments"/> <?php _e('Payments and Revenue per Bundle', 'bachbill');?><br/><br/>
		<b><?php _e('From date', 'bachbill');?>:</b> <input name="date1" size="10"><input type=button value="select" onclick="displayDatePicker('date1', false, 'mdy', '.');">  <b><?php _e('to date', 'bachbill');?>:</b> <input name="date2" size="10"><input type=button value="select" onclick="displayDatePicker('date2', false, 'mdy', '.');">  <br/>
	</div>
	</div>
	<div style="text-align: right"><input class="button-primary" type="submit" value="<?php _e('Get Report', 'bachbill');?>"/></div>
</div> 
</form> 

		<?php 
	}


?>