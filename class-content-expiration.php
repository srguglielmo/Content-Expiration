<?php
/**
 * Content Expiration.
 *
 * This file contains the Content_Expiration class.
 *
 * @link https://wordpress.org/plugins/content-expiration/
 *
 * @package ContentExpiration
 * @since 1.0.2
 */

/**
 * Content_Expiration class
 *
 * The Content_Expiration class is comprised of methods that create the widget and process expirations.
 *
 * @since 1.0.2
 */
class Content_Expiration {

	/**
	 * Construct function.
	 *
	 * The construct of this class adds the "expired" post status,
	 * the necessary JavaScript, and all other hooks.
	 *
	 * @since 1.0.2
	 */
	public function __construct() {
		// Add the custom post status, "Expired".
		add_action( 'init', array( $this, 'add_expired_post_status' ), 0 );

		// Add the js to the admin page.
		add_action( 'admin_enqueue_scripts', array( $this, 'add_js' ) );

		// Add the expire metabox.
		add_action( 'add_meta_boxes', array( $this, 'add_expiration_box' ), 10, 2 );

		// Handle the data POSTed from the meta box.
		add_action( 'save_post', array( $this, 'save_expiration_data' ), 10, 3 );

		// Add a column to the posts list.
		add_filter( 'manage_posts_columns', array( $this, 'add_expires_column' ), 5 );
		add_action( 'manage_posts_custom_column', array( $this, 'add_expires_column_data' ), 5, 2 );

		// Add a column to the pages list.
		add_filter( 'manage_pages_columns', array( $this, 'add_expires_column' ), 5 );
		add_action( 'manage_pages_custom_column', array( $this, 'add_expires_column_data' ), 5, 2 );

		// Create a cron hook.
		add_action( 'content-expiration_' . get_current_blog_id(), array( $this, 'process_cron' ) );

		// Don't schedule the task if it's already scheduled.
		if ( false === wp_next_scheduled( 'content-expiration_' . get_current_blog_id() ) ) {
			// Calculate the next hour plus 1 minute to ensure the cron job runs every min=1
			// eg 1:01, 5:01, 12:01, etc
			// This is to ensure that when the user sets the expiration by date, the post is deleted within a minute.
			$next_hour_obj = new DateTime( '+1 hour', new DateTimeZone( get_option( 'timezone_string' ) ) );
			$next_hour     = $next_hour_obj->format( 'Y-m-d h:01:00 A T' );

			wp_schedule_event(
				strtotime( $next_hour ),
				'hourly',
				'content-expiration_' . get_current_blog_id()
			);
		}

		// Cleanup when plugin is deactivated.
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	/**
	 * Add expired post status.
	 *
	 * This method adds a custom post status, 'Expired'.
	 *
	 * @since 1.0.2
	 *
	 * @see __construct().
	 */
	public function add_expired_post_status() {
		$args = array(
			'label'                     => _x( 'Expired', 'Expired Status Title', 'text_domain' ),
			'label_count'               => _n_noop( 'Expired (%s)', 'Expired (%s)', 'text_domain' ),
			'public'                    => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'exclude_from_search'       => true,
		);
		register_post_status( 'expired', $args );
	}

	/**
	 * Add Javascript.
	 *
	 * This method adds additional Javascript to the 'Create New Post' or 'Edit Post' pages.
	 *
	 * @since 1.0.2
	 *
	 * @see __construct().
	 *
	 * @param string $hook The current admin page.
	 */
	public function add_js( $hook ) {
		if ( 'post-new.php' !== $hook && 'post.php' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'content_expiration_js',
			plugins_url( 'content-expiration.js', __FILE__ ),
			array( 'jquery' ),
			true,
			true
		);
	}

	/**
	 * Add expiration meta box.
	 *
	 * This method adds the expration meta box.
	 *
	 * @since 1.0.2
	 *
	 * @see __construct().
	 *
	 * @param string  $post_type Post type.
	 * @param WP_Post $post Post object.
	 */
	public function add_expiration_box( $post_type, $post ) {
		add_meta_box( 'content-expiration-metabox', 'Content Expiration', array( $this, 'add_expiration_box_data' ), 'page' );
		add_meta_box( 'content-expiration-metabox', 'Content Expiration', array( $this, 'add_expiration_box_data' ), 'post' );
	}

	/**
	 * Add expiration meta box content.
	 *
	 * This method prints the content within the expiration meta box.
	 *
	 * @since 1.0.2
	 *
	 * @see add_expiration_box().
	 * @global WP_Post $post Post object.
	 */
	public function add_expiration_box_data() {
		global $post;

		wp_nonce_field( basename( __FILE__ ), 'content-expiration-nonce' );

		// Get the current expiration, if there is one.
		$current_expiration = get_post_meta( $post->ID, 'content_expiration', true );

		if ( ! empty( $current_expiration ) ) {
			echo '<p>Current Expiration: <span style="font-weight: bold;">' . $current_expiration . '</span></p>' . "\n";
			echo '<input type="radio" name="expiration-status" value="nochange" checked="checked">&nbsp;Keep current expiration&nbsp;&nbsp;&nbsp;&nbsp;' . "\n";
		}

		echo '<input type="radio" name="expiration-status" value="disable"' . ( empty( $current_expiration ) ? ' checked="checked"' : '' ) . '>&nbsp;Disable expiration&nbsp;&nbsp;&nbsp;&nbsp;' . "\n";
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
		for ( $x = 1; $x <= 31; $x++ ) {
			echo '			<option value="' . $x . '">' . $x . '</option>' . "\n";
		}
		echo '		</optgroup>' . "\n";
		echo '	</select>' . "\n";
		echo '	<select name="expiration-year">' . "\n";
		echo '		<optgroup label="Year">' . "\n";
		for ( $x = 0; $x <= 5; $x++ ) { // 5 years in the future
			echo '			<option value="' . ( date( 'Y' ) + $x ) . '"' . ( 0 === $x ? ' selected="selected"' : '' ) . '>' . ( date( 'Y' ) + $x ) . '</option>' . "\n";
		}
		echo '		</optgroup>' . "\n";
		echo '	</select>' . "\n";
		echo '	<select name="expiration-hour">' . "\n";
		echo '		<optgroup label="Hour">' . "\n";
		for ( $x = 1; $x <= 12; $x++ ) {
			echo '			<option value="' . $x . '">' . $x . '</option>' . "\n";
		}
		echo '		</optgroup>' . "\n";
		echo '	</select>' . "\n";
		echo '	<select name="expiration-ampm">' . "\n";
		echo '		<option value="am">AM</option>' . "\n";
		echo '		<option value="pm">PM</option>' . "\n";
		echo '	</select>' . "\n";
		echo '</div>' . "\n";
	}

	/**
	 * Save expiration data.
	 *
	 * This method processes the POST data from the expiration meta box
	 * that is submitted when a form is saved.
	 *
	 * @since 1.0.2
	 *
	 * @see __construct().
	 *
	 * @param int     $postid Post ID.
	 * @param WP_Post $post Post object.
	 * @param bool    $update Whether this is an existing post being updated or not.
	 * @return int Post ID.
	 */
	public function save_expiration_data( $postid, $post, $update ) {
		// Verify nonce.
		if ( ! isset( $_POST['content-expiration-nonce'] )
			|| ! check_admin_referer( basename( __FILE__ ), 'content-expiration-nonce' )
		) {
			return $postid;
		}

		// We only support posts and pages for now. Not custom types.
		if ( 'post' !== $post->post_type && 'page' !== $post->post_type ) {
			return $postid;
		}

		// Verify the user has edit privileges.
		if ( 'post' === $post->post_type && ! current_user_can( 'edit_post', $postid ) ) {
			return $postid;
		}

		if ( 'page' === $post->post_type && ! current_user_can( 'edit_page', $postid ) ) {
			return $postid;
		}

		// Don't do anything when autosave is running.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $postid;
		}

		// Auth checks are done. Now handle the POST data.

		// Disable the expiration.
		if ( isset( $_POST['expiration-status'] ) && 'disable' === $_POST['expiration-status'] ) {
			delete_post_meta( $postid, 'content_expiration' );
			delete_post_meta( $postid, 'content_expiration_notified' );
			return $postid;
		}

		// User wants to set expiration by number of days.
		if ( isset( $_POST['expiration-status'] ) && 'by-days' === $_POST['expiration-status'] ) {
			// Calculate future date, update/set meta.
			if ( ! isset( $_POST['expiration-days'] )
				|| empty( $_POST['expiration-days'] )
				|| ! ctype_digit( $_POST['expiration-days'] )
			) {
				return $postid;
			}

			// Quick sanity check; at least 1 day and no more than 5 years.
			if ( $_POST['expiration-days'] <= 0 || $_POST['expiration-days'] >= 1825 ) {
				return $postid;
			}

			// Calculate future date and update metadata.
			try {
				$date_time_obj = new DateTime( '+ ' . $_POST['expiration-days'] . ' days', new DateTimeZone( get_option( 'timzone_string' ) ) );
			} catch ( Exception $e ) {
				return $postid;
			}
			$future_date = $date_time_obj->format( 'Y-m-d h:00:00 A T' );
			if ( false === add_post_meta( $postid, 'content_expiration', $future_date, true ) ) {
				update_post_meta( $postid, 'content_expiration', $future_date );
			}

			// Since we updated the expiration, reset the notification flag.
			delete_post_meta( $postid, 'content_expiration_notified' );

			// Done.
			return $postid;
		}

		// User wants to set the expiration by date.
		if ( isset( $_POST['expiration-status'] ) && 'by-date' === $_POST['expiration-status'] ) {
			// Make sure all POST data is present.
			if ( ! isset( $_POST['expiration-month'] ) || empty( $_POST['expiration-month'] ) || ! ctype_digit( $_POST['expiration-month'] ) ||
				! isset( $_POST['expiration-day'] ) || empty( $_POST['expiration-day'] ) || ! ctype_digit( $_POST['expiration-day'] ) ||
				! isset( $_POST['expiration-year'] ) || empty( $_POST['expiration-year'] ) || ! ctype_digit( $_POST['expiration-year'] ) ||
				! isset( $_POST['expiration-hour'] ) || empty( $_POST['expiration-hour'] ) || ! ctype_digit( $_POST['expiration-hour'] ) ||
				! isset( $_POST['expiration-ampm'] ) || ( 'am' !== $_POST['expiration-ampm'] && 'pm' !== $_POST['expiration-ampm'] )
			) {
				return $postid;
			}

			$timestring  = $_POST['expiration-year'] . '-' . $_POST['expiration-month'] . '-' . $_POST['expiration-day'];
			$timestring .= ' ' . $_POST['expiration-hour'] . ':00:00 ' . $_POST['expiration-ampm'];

			// Invalid input from the client (eg, month 13) throws an exception.
			try {
				$date_time_obj = new DateTime( $timestring, new DateTimeZone( get_option( 'timzone_string' ) ) );
			} catch ( Exception $e ) {
				return $postid;
			}
			$future_date = $date_time_obj->format( 'Y-m-d h:i:s A T' );

			if ( add_post_meta( $postid, 'content_expiration', $future_date, true ) === false ) {
				update_post_meta( $postid, 'content_expiration', $future_date );
			}

			// Since we updated the expiration, reset the notification flag.
			delete_post_meta( $postid, 'content_expiration_notified' );

			return $postid;
		}

		// expiration-status == nochange or !isset(expiration-status) will fall through to here. Do nothing.
		return $postid;
	}

	/**
	 * Add expires column.
	 *
	 * This method adds a column named "Expires" in the posts and pages list.
	 *
	 * @since 1.0.2
	 *
	 * @param array  $post_columns An associative array of column headings.
	 * @param string $post_type The post type slug.
	 * @return array An associative array of column headings.
	 */
	public function add_expires_column( $post_columns, $post_type ) {
		$post_columns['expiration'] = 'Expiration';
		return $post_columns;
	}

	/**
	 * Add expires column data.
	 *
	 * This method adds the row data to the "Expires" column on the posts and pages list.
	 *
	 * @since 1.0.2
	 *
	 * @see add_expires_column().
	 *
	 * @param string $column_name The name of the column to display.
	 * @param int    $postid The current post ID.
	 */
	public function add_expires_column_data( $column_name, $postid ) {
		if ( 'expiration' === $column_name ) {
			$postexpired = get_post_meta( $postid, 'content_expiration', true );

			if ( empty( $postexpired ) ) {
				echo 'Never';
			} else {
				$exp = new DateTime( $postexpired, new DateTimeZone( get_option( 'timzone_string' ) ) );
				$now = new DateTime( 'now', new DateTimeZone( get_option( 'timzone_string' ) ) );
				if ( $exp->getTimestamp() <= $now->getTimestamp() ) {
					echo '<span style="font-weight: bold;">Expired</span>';
				} else {
					echo $postexpired;
				}
			}
		}
	}

	/**
	 * Check for expired posts.
	 *
	 * This method is called when the cron task executes every hour. It
	 * checks if content has expired or if it will expire within two weeks, then
	 * takes the appropriate action.
	 *
	 * @since 1.0.2
	 *
	 * @see __construct().
	 * @global wpdb $wpdb WordPress Database Access Abstraction Object.
	 */
	public function process_cron() {
		global $wpdb;

		// Select all posts that have an expiration date.
		$results = $wpdb->get_results(
			'SELECT pm.post_id, pm.meta_value, p.post_author, p.post_title
			FROM ' . $wpdb->postmeta . ' AS pm
			INNER JOIN ' . $wpdb->posts . ' AS p ON pm.post_id = p.ID
			WHERE pm.meta_key = "content_expiration"',
			OBJECT
		);

		foreach ( $results as $post ) {
			$post_id    = $post->post_id;
			$user_id    = $post->post_author;
			$post_title = $post->post_title;

			// Check to see if we should send an email notification.
			$future_obj = new DateTime( '+2 weeks', new DateTimeZone( get_option( 'timzone_string' ) ) );
			try {
				$exp_date = new DateTime( $post->meta_value, new DateTimeZone( get_option( 'timzone_string' ) ) );
			} catch ( Exception $e ) {
				continue; // Something went wrong. Skip this entry.
			}

			if ( $exp_date->getTimestamp() <= $future_obj->getTimestamp()
				&& get_post_status( $post_id ) === 'publish'
			) {
				// Send email only if not already sent.
				$sent_email = get_post_meta( $post_id, 'content_expiration_notified', true );
				if ( empty( $sent_email ) ) {
					// Send a notification email.
					$msg  = 'Hello ' . get_the_author_meta( 'display_name', $user_id ) . ",\n\n";
					$msg .= 'You are the author of a post on ' . site_url() . " that will expire in 2 weeks!\n\n";
					$msg .= 'Post: ' . $post_title . "\n\n";
					$msg .= "Upon expiration, the post will be marked as expired and hidden from visitors. It will not be deleted.\n\n";
					$msg .= "Thanks!\n";
					wp_mail( get_the_author_meta( 'user_email', $user_id ), 'Your Post Will Expire Soon!', $msg );

					if ( add_post_meta( $post_id, 'content_expiration_notified', 'yes', true ) === false ) {
						// This should never happen, but lets check anyway.
						update_post_meta( $post_id, 'content_expiration_notified', 'yes' );
					}
				}
			}

			// Check to see if the date has passed. If so, unpublish.
			$now = new DateTime( 'now', new DateTimeZone( get_option( 'timzone_string' ) ) );
			if ( $exp_date->getTimestamp() <= $now->getTimestamp()
				&& get_post_status( $post_id ) === 'publish'
			) {
				// Send a notification email.
				$msg  = 'Hello ' . get_the_author_meta( 'display_name', $user_id ) . ",\n\n";
				$msg .= 'You are the author of a post on ' . site_url() . " that has expired!\n\n";
				$msg .= 'Post: ' . $post_title . "\n\n";
				$msg .= "The post has been marked as expired and is now hidden from visitors.\n\n";
				$msg .= "Thanks!\n";
				wp_mail( get_the_author_meta( 'user_email', $user_id ), 'Post Expiration', $msg );

				// Update post status.
				wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => 'expired',
					)
				);
			}
		}
	}

	/**
	 * Deactivate cron event.
	 *
	 * This method removes the cron event when the plugin is deactivated.
	 *
	 * @since 1.0.2
	 *
	 * @see __construct().
	 */
	public function deactivate() {
		wp_unschedule_event(
			wp_next_scheduled( 'content-expiration_' . get_current_blog_id() ),
			'content-expiration_' . get_current_blog_id()
		);
	}
}
