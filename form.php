<?php
defined('ABSPATH') or die;

$ab_paypal = new AccountBuilderPaypal();
$user_data = wp_get_current_user();

if($ab_paypal->plugin_data['account_builder_paypal_test_mode'] == 'on') {
	$url = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
} else {
	$url = 'https://www.paypal.com/cgi-bin/webscr';
}
?>
<form method="post" action="<?php echo $url; ?>">
	<table class="uk-table uk-table-striped uk-text-center uk-width-2-3">
	    <thead>
	        <tr>
	            <th class="uk-text-center">Payment Method</th>
	            <th class="uk-text-center">Price</th>
	        </tr>
	    </thead>
	    <tbody>
	        <tr>
	            <td>PayPal</td>
	            <td>P<?php echo $ab_paypal->plugin_data['account_builder_paypal_amount']; ?></td>
	        </tr>
	    </tbody>
	</table>
	<input type="hidden" name="charset" value="utf-8">
    <input type="hidden" name="cmd" value="_xclick">
    <input type="hidden" name="business" value="<?php echo $ab_paypal->plugin_data['account_builder_paypal_email']; ?>">
    <input type="hidden" name="item_name" value="Pafti Forex Account Upgrade">
    <input type="hidden" name="item_number" value="1">
    <input type="hidden" name="amount" value="<?php echo $ab_paypal->plugin_data['account_builder_paypal_amount']; ?>">
    <input type="hidden" name="no_shipping" value="1">
    <input type="hidden" name="no_note" value="1">
    <input type="hidden" name="currency_code" value="PHP">
    <input type="hidden" name="paymentaction" value="sale">
    <input type="hidden" name="invoice" value="<?php echo $transaction_id.' '.$user_data->first_name.' '.$user_data->last_name; ?>">
	<input type="hidden" name="first_name" value="<?php echo $user_data->first_name; ?>">
	<input type="hidden" name="last_name" value="<?php echo $user_data->last_name; ?>">
    <input type="hidden" name="email" value="<?php echo $user_data->user_email; ?>">
    <input type="hidden" name="return" value="<?php echo $ab_paypal->plugin_data['account_builder_paypal_after_payment_redirect_url']; ?>">
    <input type="hidden" name="notify_url" value="<?php echo site_url($ab_paypal->ipn_query_string); ?>">
    <input type="hidden" name="custom" value="<?php echo $transaction_id; ?>">
    <input type="submit" class="uk-button uk-button-primary uk-float-left" value="purchase" id="purchase" />
</form>