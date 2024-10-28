<?php


function bachbill_payments() {
	$partnerId=get_option('bachbill_adminUserId');
		?>

<form id="fff" target="_blank" name="payments" method="post" action="https://api.bachbill.com/bachbill/dispatch?activity=credit&action=form">
<input type="hidden" name="partnerId" value="<?php echo $partnerId;?>"/>
<div style="margin:2em; padding: 2em; background-color: #EEE; border-radius: 1em; ">
	<div style="background-color: #DDD; border-top-left-radius: 1em; border-top-right-radius: 1em; padding:1em; border-bottom:1px solid #eee;">
		<table>
			<tr>
				<td><img style="vertical-align: middle;" width="130" src="../wp-content/plugins/a-dime-every-time/img/dime_logo.png"/></td>
				<td>
					<div style="padding: 0.5em; padding-left: 2em; color: #666; font-size: 150%; line-height: 150%;">
						<h1><?php _e('Payments', 'bachbill');?></h1><br/><br/> 
					</div>
				</td>
			</tr>
		</table>
	<div style="clear: both;"></div>
	<br/>
	<div id="account_info2" style="padding: 20px; background-color: #EEE; border-radius: 1em; box-shadow: #888 6px 6px 15px;">
		Payments are managed by the Bachbill platform.
	</div>
	<br/>
	</div>
	<br/>
	<div style="text-align: right"><input class="button-primary" type="submit" value="<?php _e('Go to payments page', 'bachbill');?>"/></div>
</div> 
</form> 

		<?php 
	}


?>