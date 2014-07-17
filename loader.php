<?php
/*
Plugin Name: BP Relative Time
Description: Dynamically adjust the relative time in BuddyPress template loops using JS.
Version: 0.1-alpha
Author: r-a-y
Author URI: http://profiles.wordpress.org/r-a-y
License: GPLv2 or later
*/

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Only load the plugin code if BuddyPress is activated.
 */
function bp_relative_time_include() {
	require( dirname( __FILE__ ) . '/bp-relative-time.php' );
}
add_action( 'bp_include', 'bp_relative_time_include' );