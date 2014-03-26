<?php

$username = get_input('username');
$user = get_user_by_username($username);

if (!$user) {
	forward('', '404');
}

$title = elgg_echo('paid_membership');

elgg_set_page_owner_guid($user->guid);
elgg_push_breadcrumb(elgg_echo('profile'), $user->getURL());
elgg_push_breadcrumb($title);


$content = elgg_view_entity($user, array('full_view' => true));

/*
if (elgg_is_admin_logged_in()) {
	$content .= '<br><br>';
	$content .= elgg_view_form('paid_membership/expiration', array(), array('user' => $user));
}
else {
 * 
 */
	$content .= '<br><br>';
	$content .= elgg_view('paid_membership/expiration', array('user' => $user));
//}

//$content .= '<br><br>';
//$content .= elgg_view('paid_membership/history', array('user' => $user));

$layout = elgg_view_layout('content', array(
	'title' => $title,
	'content' => $content,
	'filter' => false
));

echo elgg_view_page($title, $layout);