<?php
/**
 * Plugin Name: Paid Memberships Pro - AvaTax Add On
 * Plugin URI: https://www.paidmembershipspro.com/add-ons/avatax-integration
 * Description: Integrate with Avalara Tax Services
 * Version: .1
 * Author: Paid Memberships Pro
 * Author URI: https://www.paidmembershipspro.com
 * Text Domain: pmpro-avalara
 */

define( 'PMPROAVA_VERSION', '.1' );
define( 'PMPROAVA_DIR', dirname( __FILE__ ) );
define( 'PMPROAVA_BASENAME', plugin_basename( __FILE__ ) );

require_once PMPROAVA_DIR . '/classes/class-pmproava-sdk-wrapper.php';  // Connect PMPromc to AvaTax.
require_once PMPROAVA_DIR . '/includes/functions.php';                  // Miscellaneous functions.

require_once PMPROAVA_DIR . '/includes/adminpages/avatax-settings.php'; // AvaTax settings page.
require_once PMPROAVA_DIR . '/includes/adminpages/edit-level.php';      // AvaTax fields on edit level page.

/**
 * Load the languages folder for translations.
 */
function pmproava_load_textdomain() {
	load_plugin_textdomain( 'pmpro-avatax', false, basename( dirname( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'pmproava_load_textdomain' );

/**
 * Add links to the plugin action links
 *
 * @param $links (array) - The existing link array
 * @return array -- Array of links to use
 *
 */
function pmproava_add_action_links( $links ) {

	$new_links = array(
		'<a href="' . get_admin_url( null, 'options-general.php?page=pmproava_options' ) . '">' . __( 'Settings', 'pmpro-avatax' ) . '</a>',
	);
	return array_merge( $new_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'pmproava_add_action_links' );

/**
 * Add links to the plugin row meta
 *
 * @param $links - Links for plugin
 * @param $file - main plugin filename
 * @return array - Array of links
 */
function pmproava_plugin_row_meta($links, $file)
{
	if (strpos($file, 'pmpro-avatax.php') !== false) {
		$new_links = array(
			'<a href="' . esc_url('https://www.paidmembershipspro.com/add-ons/pmpro-avatax-integration/') . '" title="' . esc_attr(__('View Documentation', 'pmpro-avatax')) . '">' . __('Docs', 'pmpro-avatax') . '</a>',
			'<a href="' . esc_url('https://wwww.paidmembershipspro.com/support/') . '" title="' . esc_attr(__('Visit Customer Support Forum', 'pmpro-avatax')) . '">' . __('Support', 'pmpro-avatax') . '</a>',
		);
		$links = array_merge($links, $new_links);
	}
	return $links;
}
add_filter('plugin_row_meta', 'pmproava_plugin_row_meta', 10, 2);