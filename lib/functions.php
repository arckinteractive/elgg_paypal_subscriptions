<?php

function paypal_subscriptions_get_url($user, $subscription_name) {
	elgg_load_library('paypal');
	
	$subscription_info = paypal_subscription_get_subscription_info($subscription_name);
	
	if (!$subscription_info) {
		return false;
	}

	$paymentDetails= new PaymentDetailsType();

	$orderTotal = new BasicAmountType();
	$orderTotal->currencyID = $subscription_info['currency'];
	$orderTotal->value = 0; // always 0 for subscriptions to get token, amount is approved on submission

	$paymentDetails->OrderTotal = $orderTotal;
	$paymentDetails->PaymentAction = 'Sale';
	$paymentDetails->NotifyURL = paypal_get_ipn_url();

	$setECReqDetails = new SetExpressCheckoutRequestDetailsType();
	$setECReqDetails->PaymentDetails[0] = $paymentDetails;
	$setECReqDetails->CancelURL = elgg_get_site_url() . 'paypal/subscription/endpoint/cancel';
	$setECReqDetails->ReturnURL = elgg_get_site_url() . 'paypal/subscription/endpoint/accept/' . urlencode($subscription_info['name']);
  
	$billingAgreementDetails = new BillingAgreementDetailsType('RecurringPayments');
	$billingAgreementDetails->BillingAgreementDescription = $subscription_info['agreement'];
	$setECReqDetails->BillingAgreementDetails = array($billingAgreementDetails);

	$setECReqType = new SetExpressCheckoutRequestType();
	$setECReqType->Version = '98.0';
	$setECReqType->SetExpressCheckoutRequestDetails = $setECReqDetails;

	$setECReq = new SetExpressCheckoutReq();
	$setECReq->SetExpressCheckoutRequest = $setECReqType;
	
	$paypalService = paypal_get_service();
	
	try {
		/* wrap API method calls on the service object with a try catch */
		$setECResponse = $paypalService->SetExpressCheckout($setECReq);
	} catch (Exception $ex) {
		
		if(isset($ex) && elgg_is_admin_logged_in()) {
			$ex_message = $ex->getMessage();
			$ex_type = get_class($ex);

			if($ex instanceof PPConnectionException) {
				$ex_detailed_message = "Error connecting to " . $ex->getUrl();
			} else if($ex instanceof PPMissingCredentialException || $ex instanceof PPInvalidCredentialException) {
				$ex_detailed_message = $ex->errorMessage();
			} else if($ex instanceof PPConfigurationException) {
				$ex_detailed_message = "Invalid configuration. Please check your configuration file";
			}
			
			register_error(elgg_echo('paypal:error:response', array($ex_type, $ex_message, $ex_detailed_message)));
			return false;
		}
	}
	
	$token = false;
	if(isset($setECResponse)) {
		if($setECResponse->Ack =='Success') {
			$token = $setECResponse->Token;
		}
	}
	
	if (!$token) {
		return false;
	}
	
	// note, when generating a url, set the current page in the session for returning to
	ElggSession::offsetSet('paypal_subscription_forward_to', current_page_url());
	
	return paypal_get_ec_checkout_url($token); // gets url depending on live/sandbox settings
}



/**
 * Get the subscription information
 * 
 * @param type $subscription_name
 * @return type
 */
function paypal_subscription_get_subscription_info($subscription_name) {
	$subscriptions = elgg_trigger_plugin_hook('paypal', 'subscription_info', array(), array());
	
	$result = false;
	foreach ($subscriptions as $subscription) {
		if ($subscription['name'] == $subscription_name) {
			// we have our subscription, set up translations
			$result = $subscription;
			
			$result['agreement'] = elgg_echo('paypal:subscription:agreement:'.$subscription_name);
			
			if (!$result['currency']) {
				$result['currency'] = elgg_get_plugin_setting('currency', 'elgg_paypal');
			}
		}
	}
	
	return $result;
}


function paypal_subscriptions_is_active_subscriber($user, $subscription_name) {
	if (!elgg_instanceof($user, 'user')) {
		return false;
	}
	
	$attr = 'subscription_status_' . $subscription_name;
	
	if (strpos(strtolower($user->$attr), 'active') !== false) {
		return true;
	}
	
	return false;
}