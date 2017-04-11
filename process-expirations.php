<?php
/* This file is included in content-expiration.php's method for handling the cron event.
 * It checks to see if posts have expired (or will expire in 2 weeks) and takes the appropriate
 * action.
 */

defined('ABSPATH') or die ('No.');

global $wpdb;

// Select all posts that have an expiration date
$Results = $wpdb->get_results('SELECT pm.post_id, pm.meta_value, p.post_author, p.post_title FROM ' . $wpdb->postmeta . ' AS pm INNER JOIN ' . $wpdb->posts . ' AS p ON pm.post_id = p.ID WHERE pm.meta_key = "content_expiration"', OBJECT);

foreach ($Results as $Post) {
	$PostID = $Post->post_id;
	$UserID = $Post->post_author;
	$PostTitle = $Post->post_title;

	// Check to see if we should send an email notification
	$FutureObj = new DateTime('+2 weeks', new DateTimeZone('America/New_York'));
	try {
		$ExpDate = new DateTime($Post->meta_value, new DateTimeZone('America/New_York'));
	} catch (Exception $e) {
		continue; // Something went wrong. Skip this entry.
	}

	if ($ExpDate->getTimestamp() <= $FutureObj->getTimestamp() && get_post_status($PostID) == 'publish') {
		// Send email only if not already sent
		$SentEmail = get_post_meta($PostID, 'content_expiration_notified', true);
		if (empty($SentEmail)) {
			// send a notification email
			$Msg = "Hello " . get_the_author_meta('display_name', $UserID) . ",\n\n";
			$Msg .= 'You are the author of a post on ' . site_url() . " that will expire in 2 weeks!\n\n";
			$Msg .= 'Post: ' . $PostTitle . "\n\n";
			$Msg .= "Upon expiration, the post will be marked as expired and hidden from visitors. It will not be deleted.\n\n";
			$Msg .= "Thanks!\n";
			wp_mail(get_the_author_meta('user_email', $UserID), 'Your Post Will Expire Soon!', $Msg);

			if (add_post_meta($PostID, 'content_expiration_notified', 'yes', true) === false)
				// This should never happen, but lets check anyway.
				update_post_meta($PostID, 'content_expiration_notified', 'yes');
		}
	}

	// Check to see if the date has passed. If so, unpublish.
	$Now = new DateTime('now', new DateTimeZone('America/New_York'));
	if ($ExpDate->getTimestamp() <= $Now->getTimestamp() && get_post_status($PostID) == 'publish') {
		// send a notification email
		$Msg = "Hello " . get_the_author_meta('display_name', $UserID) . ",\n\n";
		$Msg .= 'You are the author of a post on ' . site_url() . " that has expired!\n\n";
		$Msg .= 'Post: ' . $PostTitle . "\n\n";
		$Msg .= "The post has been marked as expired and is now hidden from visitors.\n\n";
		$Msg .= "Thanks!\n";
		wp_mail(get_the_author_meta('user_email', $UserID), 'Post Expiration', $Msg);

		// upate post status
		wp_update_post(array('ID' => $PostID, 'post_status' => 'expired'));
	}
}

?>
