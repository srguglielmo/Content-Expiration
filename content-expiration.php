<?php
/*
Plugin Name: Content Expiration
Plugin URI: https://github.com/srguglielmo/Content-Expiration
Description: Expirations for posts or pages. Expired content is hidden, never deleted. Email notifications are sent to the author.
Version: 1.0.1
Author: Steve Guglielmo
License: MIT
Please see the LICENSE file for more information.
*/

defined('ABSPATH') or die ('No.');

new ContentExpiration();

class ContentExpiration {

	public function __construct() {
		// Add the custom post status, "Expired".
		add_action('init', array($this, 'add_expired_post_status'), 0);

		// Add the js to the admin page.
		add_action('admin_enqueue_scripts', array($this, 'add_media'));

		// Add the expire metabox.
		add_action('add_meta_boxes', array($this, 'add_expiration_box'), 10, 2);

		// Handle saving the data from the meta box.
		add_action('save_post', array($this, 'save_expiration_data'), 10, 3);

		// Add a db column to the posts list.
		add_filter('manage_posts_columns', array($this, 'add_expires_column'), 5);
		add_action('manage_posts_custom_column', array($this, 'add_expires_column_data'), 5, 2);

		// Add a db column to the pages list
		add_filter('manage_pages_columns', array($this, 'add_expires_column'), 5);
		add_action('manage_pages_custom_column', array($this, 'add_expires_column_data' ), 5, 2);

		// Create a cronjob hook
		add_action('content-expiration_' . get_current_blog_id(), array($this, 'handle_cron_event'));

		// Don't schedule the task if it's already scheduled
		if (wp_next_scheduled('content-expiration_' . get_current_blog_id()) === false) {
			// Calculate the next hour plus 1 minute to ensure the cron job runs every min=1
			// eg 1:01, 5:01, 12:01, etc
			// This is to ensure that when the user sets the expiration by date, the post is deleted within a minute.
			$NextHourObj = new DateTime('+1 hour', new DateTimeZone('America/New_York'));
			$NextHour = $NextHourObj->format('Y-m-d h:01:00 A T');
			wp_schedule_event(strtotime($NextHour), 'hourly', 'content-expiration_' . get_current_blog_id());
		}

		// Cleanup when plugin is deactivated
		register_deactivation_hook(__FILE__, array($this, 'deactivate'));
	}

	// Add a custom post status, 'Expired'
	public function add_expired_post_status() {
		$args = array(
			'label'						=> _x('Expired', 'Expired Status Title', 'text_domain'),
			'label_count'				=> _n_noop('Expired (%s)',  'Expired (%s)', 'text_domain'),
			'public'					=> false,
			'show_in_admin_all_list'	=> true,
			'show_in_admin_status_list'	=> true,
			'exclude_from_search'		=> true);
		register_post_status('expired', $args);
	}

	// Include the js file in the proper admin page.
	public function add_media($hook) {
		// Only add media when creating a post/page or editing a post/page
		if ($hook != 'post-new.php' && $hook != 'post.php')
			return;

		wp_enqueue_script('content_expiration_js', plugins_url('content-expiration.js', __FILE__), array('jquery'), null, true);
	}

	// Add the expiration meta box.
	public function add_expiration_box($post_type, $post) {
		add_meta_box('content-expiration-metabox', 'Content Expiration', array($this, 'add_expiration_box_data'), 'page');
		add_meta_box('content-expiration-metabox', 'Content Expiration', array($this, 'add_expiration_box_data'), 'post');
	}

	// This prints the content of the expiration meta box.
	public function add_expiration_box_data() {
		global $post;

		wp_nonce_field(basename(__FILE__), 'content-expiration-nonce');

		// Get the current expiration, if there is one
		$CurrentExpiration = get_post_meta($post->ID, 'content_expiration', true);

		if (!empty($CurrentExpiration)) {
			echo '<p>Current Expiration: <span style="font-weight: bold;">' . $CurrentExpiration . '</span></p>' . "\n";
			echo '<input type="radio" name="expiration-status" value="nochange" checked="checked">&nbsp;Keep current expiration&nbsp;&nbsp;&nbsp;&nbsp;' . "\n";
		}

		echo '<input type="radio" name="expiration-status" value="disable"' . (empty($CurrentExpiration) ? ' checked="checked"' : '') . '>&nbsp;Disable expiration&nbsp;&nbsp;&nbsp;&nbsp;' . "\n";
		echo '<input type="radio" name="expiration-status" value="by-days">&nbsp;Expire in x days&nbsp;&nbsp;&nbsp;&nbsp;' . "\n";
		echo '<input type="radio" name="expiration-status" value="by-date">&nbsp;Expire by date' . "\n";

		echo '<div id="expiration-info" class="hide-if-js">' . "\n";
		echo '<p>The author will be emailed 2 weeks prior to the expiration. Once it expires, the post or page will be set to the \'expired\' status and hidden from visitors. Content is never deleted. Please note that expirations are checked once an hour.</p>' . "\n";
		echo '</div>' . "\n";

		echo '<div id="expiration-by-days" class="hide-if-js">' . "\n";
		echo 'Number of days until expiration: <input type="text" name="expiration-days" maxlength="4" size="5" placeholder="Days" />' . "\n";
		echo '</div>' . "\n";

		echo '<div id="expiration-by-date" class="hide-if-js">' . "\n";
		echo '	<select name="expiration-month">' . "\n";
		echo '		<optgroup label="Month">' . "\n";
		echo '			<option value="01">01 - Jan</option>' . "\n";
		echo '			<option value="02">02 - Feb</option>' . "\n";
		echo '			<option value="03">03 - Mar</option>' . "\n";
		echo '			<option value="04">04 - Apr</option>' . "\n";
		echo '			<option value="05">05 - May</option>' . "\n";
		echo '			<option value="06">06 - Jun</option>' . "\n";
		echo '			<option value="07">07 - Jul</option>' . "\n";
		echo '			<option value="08">08 - Aug</option>' . "\n";
		echo '			<option value="09">09 - Sep</option>' . "\n";
		echo '			<option value="10">10 - Oct</option>' . "\n";
		echo '			<option value="11">11 - Nov</option>' . "\n";
		echo '			<option value="12">12 - Dec</option>' . "\n";
		echo '		</optgroup>' . "\n";
		echo '	</select>' . "\n";
		echo '	<select name="expiration-day">' . "\n";
		echo '		<optgroup label="Day">' . "\n";
		for ($x = 1; $x <= 31; $x++) {
			echo '			<option value="' . $x . '">' . $x . '</option>' . "\n";
		}
		echo '		</optgroup>' . "\n";
		echo '	</select>' . "\n";
		echo '	<select name="expiration-year">' . "\n";
		echo '		<optgroup label="Year">' . "\n";
		for ($x = 0; $x <= 5; $x++) { // 5 years in the future
			echo '			<option value="' . (date('Y') + $x) . '"' . ($x == 0 ? ' selected="selected"' : '') . '>' . (date('Y') + $x) . '</option>' . "\n";
		}
		echo '		</optgroup>'. "\n";
		echo '	</select>' . "\n";
		echo '	<select name="expiration-hour">' . "\n";
		echo '		<optgroup label="Hour">' . "\n";
		for ($x = 1; $x <= 12; $x++) {
			echo '			<option value="' . $x . '">' . $x . '</option>' . "\n";
		}
		echo '		</optgroup>'. "\n";
		echo '	</select>' . "\n";
		echo '	<select name="expiration-ampm">' . "\n";
		echo '		<option value="am">AM</option>' . "\n";
		echo '		<option value="pm">PM</option>' . "\n";
		echo '	</select>' . "\n";
		echo '</div>' . "\n";
	}

	/* Handle the data when a form is saved */
	public function save_expiration_data($postid, $post, $update) {
		// Verify nonce
		if (!isset($_POST['content-expiration-nonce']) || !check_admin_referer(basename(__FILE__), 'content-expiration-nonce'))
			return $postid;

		// We only support posts and pages for now. Not custom types.
		if ($post->post_type != 'post' && $post->post_type != 'page')
			return $postid;

		// Verify the user has edit privs
		if($post->post_type == "post" && !current_user_can("edit_post", $postid))
			return $postid;
		if ($post->post_type == "page" && !current_user_can("edit_page", $postid))
			return $postid;

		// Don't do anything for autosave
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
			return $postid;

		// Auth checks are done. Handle the POST data.

		// Disable the expiration.
		if (isset($_POST['expiration-status']) && $_POST['expiration-status'] == "disable") {
			delete_post_meta($postid, 'content_expiration');
			delete_post_meta($postid, 'content_expiration_notified');
			return $postid;
		}

		// User wants to set expiration by number of days
		if (isset($_POST['expiration-status']) && $_POST['expiration-status'] == "by-days") {
			// Calculate future date, update/set meta
			if (!isset($_POST['expiration-days']) || empty($_POST['expiration-days']) || !ctype_digit($_POST['expiration-days']))
				return $postid;

			// Quick sanity check; at least 1 day and no more than 5 years
			if ($_POST['expiration-days'] <= 0 || $_POST['expiration-days'] >= 1825)
				return $postid;

			// Calculate future date and update metadata
			try {
				$DT = new DateTime('+ ' . $_POST['expiration-days'] . ' days', new DateTimeZone('America/New_York'));
			} catch (Exception $e) {
				return $postid;
			}
			$FutureDate = $DT->format('Y-m-d h:00:00 A T');
			if (add_post_meta($postid, 'content_expiration', $FutureDate, true) === false)
				update_post_meta($postid, 'content_expiration', $FutureDate);

			// Since we updated the expiration, reset the notification flag
			delete_post_meta($postid, 'content_expiration_notified');

			// Done
			return $postid;
		}

		// User wants to set the expiration by date
		if (isset($_POST['expiration-status']) && $_POST['expiration-status'] == "by-date") {
			// Make sure all POST data is present
			if (
				!isset($_POST['expiration-month']) || empty($_POST['expiration-month']) || !ctype_digit($_POST['expiration-month']) ||
				!isset($_POST['expiration-day']) || empty($_POST['expiration-day']) || !ctype_digit($_POST['expiration-day']) ||
				!isset($_POST['expiration-year']) || empty($_POST['expiration-year']) || !ctype_digit($_POST['expiration-year']) ||
				!isset($_POST['expiration-hour']) || empty($_POST['expiration-hour']) || !ctype_digit($_POST['expiration-hour']) ||
				!isset($_POST['expiration-ampm']) || ($_POST['expiration-ampm'] != 'am' && $_POST['expiration-ampm'] != 'pm'))
				return $postid;

			$Timestring = $_POST['expiration-year'] . '-' . $_POST['expiration-month'] . '-' . $_POST['expiration-day'];
			$Timestring .= ' ' . $_POST['expiration-hour'] . ':00:00' . ' ' . $_POST['expiration-ampm'];

			// Invalid input from the client (eg, month 13) throws an exception.
			try {
				$DT = new DateTime($Timestring, new DateTimeZone('America/New_York'));
			} catch (Exception $e) {
				return $postid;
			}
			$FutureDate = $DT->format('Y-m-d h:i:s A T');

			if (add_post_meta($postid, 'content_expiration', $FutureDate, true) === false)
				update_post_meta($postid, 'content_expiration', $FutureDate);

			// Since we updated the expiration, reset the notification flag
			delete_post_meta($postid, 'content_expiration_notified');

			return $postid;
		}

		// expiration-status == nochange or !isset(expiration-status) will fall through to here. Do nothing.
		return $postid;
	}

	// This adds a column "Expires" in the posts or pages list.
	public function add_expires_column($col) {
		$col['expiration'] = 'Expiration';
		return $col;
	}

	// This adds the data to the expires column on the posts or pages list.
	public function add_expires_column_data($column_name, $postid) {
		if ($column_name === 'expiration') {
			$postexpired = get_post_meta($postid, 'content_expiration', true);

			if (empty($postexpired)) {
				echo 'Never';
			} else {
				$Exp = new DateTime($postexpired, new DateTimeZone('America/New_York'));
				$Now = new DateTime('now', new DateTimeZone('America/New_York'));
				if ($Exp->getTimestamp() <= $Now->getTimestamp())
					echo '<span style="font-weight: bold;">Expired</span>';
				else
					echo $postexpired;
			}
		}
	}

	// This method is called when the cron task executes every hour.
	public function handle_cron_event() {
		require_once('process-expirations.php');
	}

	// Unschedule our cron event when the plugin is deactivated.
	public function deactivate() {
		wp_unschedule_event(wp_next_scheduled('content-expiration_' . get_current_blog_id()), 'content-expiration_' . get_current_blog_id());
	}
}
