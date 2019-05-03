<?php
/**
 * Plugin Name: Account Builder Paypal
 * Description: Paypal website payments standard for Account Buiilder plugin.
 * Version: 1.0
 * Author: Ralph Aludo
 * Author URI: https://www.facebook.com/ldrlph
 * Text Domain: account-builder-paypal
 *
 * @package AccountBuilderPaypal
 **/

defined('ABSPATH') or die;

final class AccountBuilderPaypal {
	private $slug;
	public $table;
	public $db_fields = array(
		'account_builder_paypal_email',
		'account_builder_paypal_after_payment_redirect_url',
		'account_builder_paypal_amount',
		'account_builder_paypal_test_mode'
	);
	public $payment_page;
	public $plugin_data;
	public $transactions_table;
	public $ipn_query_string = 'pp-callback';

	public function __construct() {
		global $wpdb;
		$this->slug = 'account_builder_paypal';
		$this->table = $wpdb->prefix.'account_builder_config';
		$this->payment_page = $wpdb->get_row("select value from ".$this->table." where name = 'account_builder_payment_page_url'")->value;
		$this->plugin_data = $wpdb->get_results("select * from ".$this->table." where name in(".implode(',', $this->set_plugin_data()).")");
		$this->transactions_table = $wpdb->prefix . 'account_builder_transactions';

		$data = array();

		foreach($this->plugin_data as $d) {
			$data[$d->name] = $d->value;
		}

		$this->plugin_data = $data;
	}

	public function activate() {
		if(!class_exists('AccountBuilder')) {
			wp_die('Account Builder plugin is not installed or deactivated, Account Builder Paypal plugin cannot not be activated. <a href="plugins.php"#>Go back to plugins page</a>');
		}

		global $wpdb;

		foreach($this->db_fields as $field) {
			$config_id = $wpdb->get_row("select id from ".$this->table." where name = '".$field."'");

			if($config_id == '') {
				$wpdb->insert($this->table, array('name' => $field, 'value' => ''));
			}
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS ".$this->transactions_table." (
			id int NOT NULL AUTO_INCREMENT,
			user_id int NOT NULL,
			payment_method VARCHAR(50) NOT NULL,
			status int(1) NOT NULL COMMENT '0 - Nothing, 1 - Pending, 2 - Completed, 3 - Refunded',
			amount DOUBLE(10,2) NOT NULL,
			date_added datetime NOT NULL,
			date_transact datetime NULL,
			PRIMARY KEY  (id)
			) $charset_collate;";

		require_once(ABSPATH.'wp-admin/includes/upgrade.php');
		dbDelta($sql);

		add_rewrite_rule('^'.$this->ipn_query_string.'/?$','index.php?ipn_callback=1&post_type=custom_post_type','top');
		flush_rewrite_rules();
	}

	public function deactivate() {
		flush_rewrite_rules();
	}

	public function settings_link($links) {
		array_push($links, '<a href="admin.php?page='.$this->slug.'">Settings</a>');
		return $links;
	}

	public function ajax() {
		session_start();
		global $wpdb;

		$user = wp_get_current_user();
		$transaction = $wpdb->get_row("select id from ".$this->transactions_table." where user_id = '".$user->ID."' and status = '0' and payment_method = 'paypal'");

		if(is_object($transaction)) {
			$transaction_id = $transaction->id;
		} else {
			$wpdb->insert($this->transactions_table, array(
				'user_id' => $user->ID,
				'date_added' => date('Y-m-d H:i:s'),
				'amount' => $this->plugin_data['account_builder_paypal_amount'],
				'payment_method' => 'paypal'
			));

			$transaction_id = $wpdb->insert_id;
		}

		require 'form.php';
		wp_die();
	}

	public function set_plugin_data() {
		global $wpdb;
		$data = array();

		foreach($this->db_fields as $field) {
			$data[] = "'".$field."'";
		}

		return $data;
	}

	public function set_query_var($vars) {
	    array_push($vars, 'ipn_callback');
	    return $vars;
	}

	public function ipn_handler() {
		if(preg_match('/'.$this->ipn_query_string.'\/?$/', $_SERVER['REQUEST_URI']) && $_POST) {
			global $wpdb;

			$transaction = $wpdb->get_row("select * from ".$this->transactions_table." where id = '".$_POST['custom']."' and status = '0' and payment_method = 'paypal'");
		
			if(is_object($transaction)) {
				$request = 'cmd=_notify-validate';

				foreach ($_POST as $key => $value) {
					$request .= '&' . $key . '=' . urlencode(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
				}

				if ($this->plugin_data['account_builder_paypal_test_mode'] == 'off') {
					$curl = curl_init('https://www.paypal.com/cgi-bin/webscr');
				} else {
					$curl = curl_init('https://www.sandbox.paypal.com/cgi-bin/webscr');
				}

				curl_setopt($curl, CURLOPT_POST, true);
				curl_setopt($curl, CURLOPT_POSTFIELDS, $request);
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($curl, CURLOPT_HEADER, false);
				curl_setopt($curl, CURLOPT_TIMEOUT, 30);
				curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

				$response = curl_exec($curl);

				if (!$response) {
					error_log('PP_STANDARD :: CURL failed ' . curl_error($curl) . '(' . curl_errno($curl) . ')');
				}

				if((strcmp($response, 'VERIFIED') == 0 || strcmp($response, 'UNVERIFIED') == 0) && isset($_POST['payment_status'])) {
					$errors = array();

					if($_POST['mc_gross'] < $transaction->amount) {
						$errors[] = 'PP_STANDARD :: TOTAL PAID MISMATCH! ' . $_POST['mc_gross'] . ' TRANSACTION ID: ' . $_POST['custom'] . ' TRANSACTION AMOUNT: ' . $transaction->amount;
					}

					if($_POST['receiver_email'] != $this->plugin_data['account_builder_paypal_email']) {
						$errors[] = 'PP_STANDARD :: RECEIVER EMAIL MISMATCH!! ' . $_POST['receiver_email'] . ' TRANSACTION ID: ' . $_POST['custom'];
					}

					if(count($errors) == 0) {
						$wpdb->update($this->transactions_table, array('status' => 2, 'date_transact' => date('Y-m-d H:i:s')), array('id' => $_POST['custom']));
						$u = new WP_User($transaction->user_id);
						$u->remove_role('free_account');
						$u->set_role('paid_account');
					} else {
						foreach($errors as $error) {
							error_log($error);
						}
					}
				}
			}
		}
	}
}

$ab_paypal = new AccountBuilderPaypal();

register_activation_hook(__FILE__, array($ab_paypal, 'activate'));
register_deactivation_hook(__FILE__, array($ab_paypal, 'deactivate'));
add_filter('plugin_action_links_'.plugin_basename(__FILE__), array($ab_paypal, 'settings_link'));
add_action('wp_ajax_ab_paypal_ajax', array($ab_paypal, 'ajax'));
add_action('wp_ajax_nopriv_ab_paypal_ajax', array($ab_paypal, 'ajax'));
add_action('query_vars', array($ab_paypal, 'set_query_var'));
add_action('init', array($ab_paypal, 'ipn_handler'));