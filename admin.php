<?php
defined('ABSPATH') or die;

global $wpdb;
$account_builder_paypal = new AccountBuilderPaypal();


$update_success = false;
$errors = array();
$fields = array();

if($_POST) {
	$post['account_builder_paypal_email'] = esc_sql($_POST['account_builder_paypal_email']);
	$post['account_builder_paypal_after_payment_redirect_url'] = esc_sql($_POST['account_builder_paypal_after_payment_redirect_url']);
	$post['account_builder_paypal_amount'] = esc_sql($_POST['account_builder_paypal_amount']);
	$post['account_builder_paypal_test_mode'] = (isset($_POST['account_builder_paypal_test_mode'])) ? 'on' : 'off';

	if($post['account_builder_paypal_email'] == '') {
		$errors[] = 'Please enter an email';
	} else {
		if(!is_email($post['account_builder_paypal_email'])) {
			$errors[] = 'Please enter a valid email';
		}
	}

	if(!preg_match('/^https?:\/\/[^\s]+$/', $post['account_builder_paypal_after_payment_redirect_url'])) {
		$errors[] = 'Please enter a valid URL';
	}

	if(!is_numeric($post['account_builder_paypal_amount'])) {
		$errors[] = 'Please enter a valid amount';
	}

	if(count($errors) == 0) {
		foreach($post as $key => $field) {
			$wpdb->update($account_builder_paypal->table, array('value' => $field), array('name' => $key));
		}

		$update_success = true;
	}
}

$db_fields = array();

foreach($account_builder_paypal->db_fields as $field) {
	$db_fields[] = "'".$field."'";
}

$fields = $wpdb->get_results("SELECT * FROM ".$account_builder_paypal->table." WHERE name IN(".implode(',', $db_fields).")");

foreach($fields as $value) {
	$values[$value->name] = $value->value;
}
?>
<div class="wrap">
	<h1>Account Builder Paypal</h1>
	<?php if($update_success) { ?>
		<div id="setting-error-settings_updated" class="updated settings-error notice is-dismissible"> 
			<p><strong>Settings saved.</strong></p>
			<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
		</div>
	<?php } ?>
	<?php if(count($errors) > 0) { ?>
		<div id="setting-error-settings_updated" class="error settings-error notice is-dismissible"> 
			<p><strong><?php echo implode('<div></div>', $errors); ?></strong></p>
			<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
		</div>
	<?php } ?>
	<?php
		$tab_active = 'general_options';
		
		if(isset($_GET['tab']) && $_GET['tab'] == 'transactions') {
			$tab_active = 'transactions';
		}
	?>
    <h2 class="nav-tab-wrapper">
        <a href="?page=account_builder_paypal&tab=general_options" class="nav-tab<?php echo ($tab_active == 'general_options') ? ' nav-tab-active' : ''; ?>">General Options</a>
        <a href="?page=account_builder_paypal&tab=transactions" class="nav-tab<?php echo ($tab_active == 'transactions') ? ' nav-tab-active' : ''; ?>">Transactions</a>
    </h2>
    <div style="margin-left:.5em;">
			<?php if($tab_active == 'general_options'): ?>
			<form method="post" action="">
				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row"><label for="account_builder_paypal_email">Email:</label></th>
							<td><input type="text" name="account_builder_paypal_email" class="regular-text" value="<?php echo (isset($post['account_builder_paypal_email'])) ? $post['account_builder_paypal_email'] : $values['account_builder_paypal_email']; ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="account_builder_paypal_after_payment_redirect_url">After Payment Redirect (URL):</label></th>
							<td><input type="text" name="account_builder_paypal_after_payment_redirect_url" class="regular-text" value="<?php echo (isset($post['account_builder_paypal_after_payment_redirect_url'])) ? $post['account_builder_paypal_after_payment_redirect_url'] : $values['account_builder_paypal_after_payment_redirect_url']; ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="account_builder_paypal_amount">Amount (Peso):</label></th>
							<td><input type="text" name="account_builder_paypal_amount" class="regular-text" value="<?php echo (isset($post['account_builder_paypal_amount'])) ? $post['account_builder_paypal_amount'] : $values['account_builder_paypal_amount']; ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="account_builder_paypal_test_mode">Test Mode:</label></th>
							<?php 
								$check = '';

								if(isset($post['account_builder_paypal_test_mode'])) {
									if($post['account_builder_paypal_test_mode'] == 'on') {
										$check = ' checked="checked"';
									}
								} else {
									if($values['account_builder_paypal_test_mode'] == 'on') {
										$check = ' checked="checked"';
									}
								}
							?>
							<td><input type="checkbox" name="account_builder_paypal_test_mode"<?php echo $check; ?> /></td>
						</tr>
					</tbody>
				</table>
			<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes"></p>
		</form>
		<?php else: ?>
		<?php
			$extra_sql = "";

			if((isset($_GET['keyword']) && $_GET['keyword'] != '') && (isset($_GET['field']) && $_GET['field'] != '')) {
				$field = $_GET['field'];
				$keyword = esc_sql($_GET['keyword']);

				switch($field) {
					case 'id':
						$extra_sql = "and id = '$keyword'";
					break;
					case 'amount':
						$extra_sql = "and amount = '$keyword'";
					break;
					case 'name':
						$extra_sql = "and (select group_concat(meta_value separator ' ') from ".$wpdb->prefix."usermeta where meta_key in('first_name','last_name') and user_id = abpt.user_id) like '%$keyword%'";
					break;
					default:
						//Nothing to do
				}
			}

			$sql = "select abpt.id,
				(select group_concat(meta_value separator ' ') from ".$wpdb->prefix."usermeta where meta_key in('first_name','last_name') and user_id = abpt.user_id) as name,
				abpt.status,
				abpt.amount,
				abpt.date_transact 
				from ".$account_builder_paypal->transactions_table." abpt
				where abpt.status != 0 and abpt.payment_method = 'paypal'
				$extra_sql
				order by abpt.date_transact desc";

			$transactions = $wpdb->get_results($sql);
		?>
		<form method="get" action="<?php echo admin_url('admin.php'); ?>">
			<p class="search-box" style="margin: 10px 0px;">
				<label class="screen-reader-text" for="post-search-input">Search Transactions:</label>
				<label>Search: </label>
				<select name="field">
					<option value=""></option>
					<option value="id"<?php echo (isset($_GET['field']) && $_GET['field'] == 'id') ? ' selected="selected"' : ''; ?>>Transaction ID</option>
					<option value="name"<?php echo (isset($_GET['field']) && $_GET['field'] == 'name') ? ' selected="selected"' : ''; ?>>Name</option>
					<option value="amount"<?php echo (isset($_GET['field']) && $_GET['field'] == 'amount') ? ' selected="selected"' : ''; ?>>Amount</option>
					<option value="date"<?php echo (isset($_GET['field']) && $_GET['field'] == 'date') ? ' selected="selected"' : ''; ?>>Date</option>
				</select>
				<input type="search" id="post-search-input" name="keyword" value="<?php echo (isset($_GET['keyword'])) ? $_GET['keyword'] : ''; ?>">
				<input type="submit" id="search-submit" class="button" value="Search Transactions">
				<input type="hidden" name="page" value="account_builder_paypal" />
				<input type="hidden" name="tab" value="transactions" />
			</p>
			<table class="wp-list-table widefat fixed striped pages">
				<thead>
					<tr>
						<th class="manage-column">Transaction ID</th>
						<th class="manage-column">Name</th>
						<th class="manage-column">Amount</th>
						<th class="manage-column">Date</th>
					</tr>
				</thead>
				<tbody>
					<?php if(count($transactions) > 0): ?>
					<?php foreach($transactions as $transaction): ?>
					<tr>
						<td><?php echo $transaction->id; ?></td>
						<td><?php echo $transaction->name; ?></td>
						<td>P<?php echo number_format($transaction->amount, 2); ?></td>
						<td><?php echo date('F j, Y', strtotime($transaction->date_transact)); ?></td>
					</tr>
					<?php endforeach; ?>
					<?php else: ?>
						<tr class="no-items"><td class="colspanchange" colspan="5">No transactions found.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</form>			
		<?php endif; ?>
    </div>
</div>