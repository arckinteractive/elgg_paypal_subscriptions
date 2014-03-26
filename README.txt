Usage:

IMPORTANT!!!!
- BEFORE USING THIS PLUGIN YOU MUST SET THE IPN (INSTANT PAYMENT NOTIFICATION) URL IN YOUR PAYPAL ACCOUNT
- This url should be [url]/paypal/ipn
- This plugin cannot receive notifications about successful transactions or cancellations without this


Subscriptions are defined by an array of parameters and gathered on the plugin hook

'paypal', 'subscription_info'

To add a subscription in a way that doesn't affect subscriptions from other plugins use something like

elgg_register_plugin_hook_handler('paypal', 'subscription_info', 'myplugin_paypal_subscriptions');


function myplugin_paypal_subscriptions($hook, $type, $return, $params) {
    $subscription = array(
        'name' => 'mypluginSubscriptionName',
        'currency' => 'USD',
        'amount' => '55.00',
        'period' => 'Month', // Day, Week, Month, SemiMonth, Year
        'frequency' => 1, // number of periods in a billing cycle, cannot exceed a years worth
        'cycles' => 0, // how many billing cycles to process, 0 for indefinite
    );

    $return[] = $subscription;

    return $return;
}

Notes:
The name should be alpha_numeric with no spaces
A language string of 'paypal:subscription:agreement:MypluginSubscriptionName' should be registered, this language string
should describe the terms of the subscription in easy to understand language.
This text will show on the paypal checkout page

eg. "Premium content: $55.00 per month on an ongoing basis until canceled"
    "Membership subscription: $20 per month until canceled"
    "Membership subscription: $20 per week for 6 weeks"






You can then get a unique url for members to sign up for your subscription by calling

$url = paypal_subscriptions_get_url($user, 'myplugin_subscription_name');

You can check if a user is currently active with your subscription

$bool = paypal_subscriptions_is_active_subscriber($user, 'myplugin_subscription_name');




Plugin hooks are triggered when subscriptions are activated and canceled
'paypal', 'subscription_activate'

'paypal', 'subscription_cancel'


Params for both include: array(
    'user' => $user, //ElggUser - the user affected by the subscription,
    'subscription_name' => 'myplugin_subscription_name'
)