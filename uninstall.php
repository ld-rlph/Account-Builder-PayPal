<?php
if (!defined("WP_UNINSTALL_PLUGIN")) {
	exit();
}

global $wpdb;

if(!class_exists('AccountBuilderDragonpay') && !class_exists('AccountBuilderPesopay')) {
	$wpdb->query("DROP TABLE IF EXISTS ".$wpdb->prefix."account_builder_transactions");
}

$wpdb->query("DELETE FROM ".$wpdb->prefix."account_builder_config WHERE name IN('account_builder_paypal_email','account_builder_paypal_after_payment_redirect_url','account_builder_paypal_amount','account_builder_paypal_test_mode')");