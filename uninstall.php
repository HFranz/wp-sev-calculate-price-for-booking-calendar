<?php
/**
 * Removes the stored price-tier settings when the plugin is deleted.
 *
 * @package sevmatic
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

const SEV_BCP_UNINSTALL_OPTION_KEY = 'sevmatic_bcp_settings';

/**
 * Deletes the plugin's option on the current site.
 */
function sev_bcp_uninstall_current_site(): void {
	delete_option( SEV_BCP_UNINSTALL_OPTION_KEY );
}

/**
 * Cleans up the current site, or every site on a multisite network.
 */
function sev_bcp_uninstall(): void {
	if ( ! is_multisite() ) {
		sev_bcp_uninstall_current_site();
		return;
	}

	$site_ids = get_sites( array( 'fields' => 'ids' ) );

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		sev_bcp_uninstall_current_site();
		restore_current_blog();
	}
}
sev_bcp_uninstall();
