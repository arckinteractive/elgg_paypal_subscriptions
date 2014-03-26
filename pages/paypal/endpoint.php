<?php

elgg_load_library('paypal');
$action = get_input('paypal_action');
$forward = '';

switch($action) {
	case 'cancel':
		register_error(elgg_echo('paid_membership:paypal:cancel'));
		break;

	case 'subscription':
		$currency = elgg_get_plugin_setting('currency', 'paid_membership');
		$amount = '55.00';
		$frequency = elgg_echo('month');
		
		$profileDetails = new RecurringPaymentsProfileDetailsType();
		$profileDetails->BillingStartDate = date(DATE_ATOM);

		$paymentBillingPeriod = new BillingPeriodDetailsType();
		$paymentBillingPeriod->BillingFrequency = 1;
		$paymentBillingPeriod->BillingPeriod = "Month";
		$paymentBillingPeriod->Amount = new BasicAmountType($currency, $amount);

		$scheduleDetails = new ScheduleDetailsType();
		$scheduleDetails->Description = elgg_echo('paid_membership:recurringpayment:description', array($amount, $frequency));;
		$scheduleDetails->PaymentPeriod = $paymentBillingPeriod;

		$createRPProfileRequestDetails = new CreateRecurringPaymentsProfileRequestDetailsType();
		$createRPProfileRequestDetails->Token = $_REQUEST['token'];

		$createRPProfileRequestDetails->ScheduleDetails = $scheduleDetails;
		$createRPProfileRequestDetails->RecurringPaymentsProfileDetails = $profileDetails;

		$createRPProfileRequest = new CreateRecurringPaymentsProfileRequestType();
		$createRPProfileRequest->CreateRecurringPaymentsProfileRequestDetails = $createRPProfileRequestDetails;

		$createRPProfileReq = new CreateRecurringPaymentsProfileReq();
		$createRPProfileReq->CreateRecurringPaymentsProfileRequest = $createRPProfileRequest;

		$paypalService = paid_membership_paypal_config();
		$createRPProfileResponse = $paypalService->CreateRecurringPaymentsProfile($createRPProfileReq);
		
		var_dump($_REQUEST['token']);
		echo '<pre>' . print_r($createRPProfileResponse, 1) . '</pre>'; exit;
		break;
	default:
		forward('', '404');
		break;
}

forward($forward);