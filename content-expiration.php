<?php
/**
 * Plugin Name: Content Expiration
 * Plugin URI: https://wordpress.org/plugins/content-expiration/
 * Description: Expirations for posts or pages. Expired content is hidden, never deleted. Email notifications are sent to the author.
 * Version: 1.1.0
 * Author: Steve Guglielmo
 * License: MIT
 *
 * Please see the LICENSE file for more information.
 *
 * @package ContentExpiration
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No script kiddies please!' );
}

require_once 'class-content-expiration.php';

new Content_Expiration();
