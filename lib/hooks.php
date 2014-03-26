<?php

/**
 * handle paypal page routing for subscriptions
 * 
 * @param type $hook
 * @param type $type
 * @param type $return
 * @param type $params
 * @return boolean
 */
function paypal_subscriptions_pagehandler($hook, $type, $return, $params) {
	$page = $return['segments'];
	
	if ($page[0] != 'subscription') {
		return $return;
	}
	
	switch ($page[1]) {
		case 'endpoint':
			elgg_load_library('paypal');
			if ($page[2] == 'cancel') {
				// transaction has been canceled
				register_error(elgg_echo('paypal_subscriptions:canceled'));
				$forward = ElggSession::offsetGet('paypal_subscription_forward_to');
				ElggSession::offsetUnset('paypal_subscription_forward_to');
				
				forward($forward);
				exit;
			}
			
			if ($page[2] == 'accept') {
				$subscription_name = urldecode($page[3]);
				
				$subscription_info = paypal_subscription_get_subscription_info($subscription_name);
				
				$profileDetails = new RecurringPaymentsProfileDetailsType();
				$profileDetails->BillingStartDate = date(DATE_ATOM);

				$paymentBillingPeriod = new BillingPeriodDetailsType();
				$paymentBillingPeriod->BillingFrequency = $subscription_info['frequency'];
				$paymentBillingPeriod->BillingPeriod = $subscription_info['period'];
				$paymentBillingPeriod->Amount = new BasicAmountType($subscription_info['currency'], $subscription_info['amount']);

				$scheduleDetails = new ScheduleDetailsType();
				$scheduleDetails->Description = $subscription_info['agreement'];
				$scheduleDetails->PaymentPeriod = $paymentBillingPeriod;

				$createRPProfileRequestDetails = new CreateRecurringPaymentsProfileRequestDetailsType();
				$createRPProfileRequestDetails->Token = $_REQUEST['token'];

				$createRPProfileRequestDetails->ScheduleDetails = $scheduleDetails;
				$createRPProfileRequestDetails->RecurringPaymentsProfileDetails = $profileDetails;

				$createRPProfileRequest = new CreateRecurringPaymentsProfileRequestType();
				$createRPProfileRequest->CreateRecurringPaymentsProfileRequestDetails = $createRPProfileRequestDetails;

				$createRPProfileReq = new CreateRecurringPaymentsProfileReq();
				$createRPProfileReq->CreateRecurringPaymentsProfileRequest = $createRPProfileRequest;

				$paypalService = paypal_get_service();
				$createRPProfileResponse = $paypalService->CreateRecurringPaymentsProfile($createRPProfileReq);
		
				if ($createRPProfileResponse->Ack == 'Success') {
					// we did it, lets set the status
					$user = elgg_get_logged_in_user_entity();
					
					if ($user) {
						$attr = 'subscription_status_' . $subscription_info['name'];
						$user->$attr = $createRPProfileResponse->CreateRecurringPaymentsProfileResponseDetails->ProfileStatus;
						
						$attr = 'subscription_id_' . $subscription_info['name'];
						$user->$attr = $createRPProfileResponse->CreateRecurringPaymentsProfileResponseDetails->ProfileID;
						
						// store reverse metadata for getting subscription name from profile ID
						$attr = 'subscription_name_' . $createRPProfileResponse->CreateRecurringPaymentsProfileResponseDetails->ProfileID;
						$user->$attr = $subscription_info['name'];
						
						// store recurring payment ids as an array to identify our user
						$ids = $user->paypal_recurring_payment_ids;
						
						if (!is_array($ids) && $ids) {
							$ids = array($ids);
						}
						else {
							$ids = array();
						}
						
						$ids[] = $createRPProfileResponse->CreateRecurringPaymentsProfileResponseDetails->ProfileID;
						
						$user->paypal_recurring_payment_ids = $ids;
						
						elgg_trigger_plugin_hook('paypal', 'subscription_activate', array('user' => $user, 'subscription_info' => $subscription_info, 'response' => $createRPProfileResponse), true);
						
						system_message(elgg_echo('paypal_subscriptions:profile:success'));
					}
				}
				else {
					register_error(elgg_echo('paypal_subscriptions:profile:error'));
				}
				
				$forward = ElggSession::offsetGet('paypal_subscription_forward_to');
				ElggSession::offsetUnset('paypal_subscription_forward_to');
				
				forward($forward);
			}
			
			break;
		
		default:
			forward('', '404');
			break;
	}
	
	return true;
}



function paypal_subscriptions_ipn_save($hook, $type, $return, $params) {
	$ia = elgg_set_ignore_access(true);
	
	$log = $return;
	
	switch ($params['txn']->txn_type) {
		case 'recurring_payment':
			
			$log->recurring_payment_id = $params['txn']->recurring_payment_id;
		
			// this is one of ours, get the owner and resave with the new owner
			$users = elgg_get_entities_from_metadata(array(
				'type' => 'user',
				'metadata_name_value_pairs' => array(
					'name' => 'paypal_recurring_payment_ids',
					'value' => $params['txn']->recurring_payment_id
				)
			));
		
			if ($users) {
				$attr = 'subscription_name_' . $params['txn']->recurring_payment_id;
				$subscription_name = $users[0]->$attr;
				$log->subscription_name = $subscription_name;
			
				$log->owner_guid = $users[0]->guid;
				$log->container_guid = $users[0]->guid;
				$log->save();
				
				// ensure user is marked as active
				if ($params['txn']->payment_status == 'Completed') {
					$attr = 'subscription_status_' . $subscription_name;
					$users[0]->$attr = 'active';
				}
			}
			break;
			
		case 'recurring_payment_profile_created':
			$log->recurring_payment_id = $params['txn']->recurring_payment_id;
			
			$users = elgg_get_entities_from_metadata(array(
				'type' => 'user',
				'metadata_name_value_pairs' => array(
					'name' => 'paypal_recurring_payment_ids',
					'value' => $params['txn']->recurring_payment_id
				)
			));
			
			if ($users) {
				$attr = 'subscription_name_' . $params['txn']->recurring_payment_id;
				$subscription_name = $users[0]->$attr;
				$log->subscription_name = $subscription_name;
			
				$log->owner_guid = $users[0]->guid;
				$log->container_guid = $users[0]->guid;
				$log->save();
			}
			break;
			
		case 'recurring_payment_profile_cancel':
			
			$log->recurring_payment_id = $params['txn']->recurring_payment_id;
			
			$users = elgg_get_entities_from_metadata(array(
				'type' => 'user',
				'metadata_name_value_pairs' => array(
					'name' => 'paypal_recurring_payment_ids',
					'value' => $params['txn']->recurring_payment_id
				)
			));
			
			if ($users) {
				$attr = 'subscription_name_' . $params['txn']->recurring_payment_id;
				$subscription_name = $users[0]->$attr;
				$log->subscription_name = $subscription_name;
			
				$log->owner_guid = $users[0]->guid;
				$log->container_guid = $users[0]->guid;
				$log->save();
				
				// mark user as canceled
				$attr = 'subscription_status_' . $subscription_name;
				$users[0]->$attr = 'canceled';
				
				$subscription_info = paypal_subscription_get_subscription_info($subscription_name);
				elgg_trigger_plugin_hook('paypal', 'subscription_cancel', array('user' => $users[0], 'transaction' => $params['txn'], 'log' => $log, 'subscription_info' => $subscription_info), true);
			}
			break;
	}
	
	elgg_set_ignore_access($ia);
	
	return $log;
}