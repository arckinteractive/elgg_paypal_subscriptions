<?php

require_once 'lib/hooks.php';
require_once 'lib/functions.php';

elgg_register_event_handler('init', 'system', 'paypal_subscriptions_init');

function paypal_subscriptions_init() {
	elgg_register_plugin_hook_handler('route', 'paypal', 'paypal_subscriptions_pagehandler');
	
	elgg_register_plugin_hook_handler('paypal', 'ipn_save', 'paypal_subscriptions_ipn_save');
}