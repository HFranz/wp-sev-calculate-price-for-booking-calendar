<?php
/**
 * Plugin Name:       SEV Calculate Price for Booking Calendar
 * Description:       Calculates and displays the booking price for Booking Calendar (WPBC) based on the number of selected days and configurable per-day price tiers, filling the [cost_hint] shortcode that Booking Calendar Free leaves inactive.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            sevmatic
 * Author URI:        https://sevmatic.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sev-calculate-price-for-booking-calendar
 * Domain Path:       /languages
 *
 * @package sevmatic
 */

defined( 'ABSPATH' ) || exit;

define( 'SEV_BCP_VERSION', '1.0.0' );
define( 'SEV_BCP_FILE', __FILE__ );
define( 'SEV_BCP_URL', plugin_dir_url( __FILE__ ) );
define( 'SEV_BCP_PATH', plugin_dir_path( __FILE__ ) );

require_once SEV_BCP_PATH . 'includes/price-calculator.php';
require_once SEV_BCP_PATH . 'includes/settings.php';
require_once SEV_BCP_PATH . 'includes/admin-settings.php';
require_once SEV_BCP_PATH . 'includes/wpbc-integration.php';

/**
 * Shows an admin notice when Booking Calendar isn't active, since this
 * plugin only ever does something on top of it.
 */
add_action(
	'admin_notices',
	function () {
		if ( sevmatic_bcp_wpbc_is_available() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		?>
		<div class="notice notice-warning">
			<p>
				<?php
				esc_html_e(
					'SEV Calculate Price for Booking Calendar requires the "Booking Calendar" plugin to be active. Price calculation is currently inactive.',
					'sev-calculate-price-for-booking-calendar'
				);
				?>
			</p>
		</div>
		<?php
	}
);
