<?php
/**
 * Admin settings page: price tiers (day ranges) and price display format.
 *
 * @package sevmatic
 */

defined( 'ABSPATH' ) || exit;

const SEVMATIC_BCP_SETTINGS_PAGE_SLUG = 'sevmatic-bcp-settings';
const SEVMATIC_BCP_NONCE_ACTION       = 'sevmatic_bcp_save_settings';
const SEVMATIC_BCP_NONCE_NAME         = 'sevmatic_bcp_nonce';

add_action(
	'admin_menu',
	function () {
		add_options_page(
			__( 'Booking Price Tiers', 'sev-calculate-price-for-booking-calendar' ),
			__( 'Booking Price Tiers', 'sev-calculate-price-for-booking-calendar' ),
			'manage_options',
			SEVMATIC_BCP_SETTINGS_PAGE_SLUG,
			'sevmatic_bcp_render_settings_page'
		);
	}
);

add_action( 'admin_init', 'sevmatic_bcp_maybe_save_settings' );

add_action(
	'admin_enqueue_scripts',
	function ( string $hook ) {
		if ( 'settings_page_' . SEVMATIC_BCP_SETTINGS_PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'sevmatic-bcp-admin-tiers',
			SEV_BCP_URL . 'public/js/admin-tiers-repeater.js',
			array(),
			SEV_BCP_VERSION,
			true
		);

		wp_enqueue_style(
			'sevmatic-bcp-admin',
			SEV_BCP_URL . 'public/css/admin.css',
			array(),
			SEV_BCP_VERSION
		);
	}
);

/**
 * Handles the settings form submission.
 *
 * @return void
 */
function sevmatic_bcp_maybe_save_settings(): void {

	if ( ! isset( $_POST['sevmatic_bcp_save'] ) ) {
		return;
	}

	if ( ! isset( $_POST[ SEVMATIC_BCP_NONCE_NAME ] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ SEVMATIC_BCP_NONCE_NAME ] ) ), SEVMATIC_BCP_NONCE_ACTION ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized field-by-field in sevmatic_bcp_sanitize_settings().
	$raw = isset( $_POST['sevmatic_bcp'] ) && is_array( $_POST['sevmatic_bcp'] ) ? wp_unslash( $_POST['sevmatic_bcp'] ) : array();

	update_option( SEVMATIC_BCP_OPTION_KEY, sevmatic_bcp_sanitize_settings( $raw ) );

	add_action(
		'admin_notices',
		function () {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Price tiers saved.', 'sev-calculate-price-for-booking-calendar' ); ?></p>
			</div>
			<?php
		}
	);
}

/**
 * Renders one price-tier row (used both for stored rows and the empty template row).
 *
 * @param int                             $index Row index, used in input names.
 * @param array{from:int,to:?int,price:float}|null $tier  Tier values, or null for an empty row.
 *
 * @return void
 */
function sevmatic_bcp_render_tier_row( int $index, ?array $tier = null ): void {
	$from  = null !== $tier ? (string) $tier['from'] : '';
	$to    = ( null !== $tier && null !== $tier['to'] ) ? (string) $tier['to'] : '';
	$price = null !== $tier ? (string) $tier['price'] : '';
	?>
	<tr class="sevmatic-bcp-tier-row">
		<td>
			<input type="number" min="1" step="1" class="small-text"
				   name="sevmatic_bcp[tiers][<?php echo esc_attr( $index ); ?>][from]"
				   value="<?php echo esc_attr( $from ); ?>" required />
		</td>
		<td>
			<input type="number" min="1" step="1" class="small-text"
				   name="sevmatic_bcp[tiers][<?php echo esc_attr( $index ); ?>][to]"
				   value="<?php echo esc_attr( $to ); ?>"
				   placeholder="<?php esc_attr_e( 'unlimited', 'sev-calculate-price-for-booking-calendar' ); ?>" />
		</td>
		<td>
			<input type="number" min="0" step="0.01" class="small-text"
				   name="sevmatic_bcp[tiers][<?php echo esc_attr( $index ); ?>][price]"
				   value="<?php echo esc_attr( $price ); ?>" required />
		</td>
		<td>
			<button type="button" class="button sevmatic-bcp-remove-row">
				<?php esc_html_e( 'Remove', 'sev-calculate-price-for-booking-calendar' ); ?>
			</button>
		</td>
	</tr>
	<?php
}

/**
 * Renders the settings page.
 *
 * @return void
 */
function sevmatic_bcp_render_settings_page(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings = sevmatic_bcp_get_settings();
	?>
	<div class="wrap sevmatic-bcp-settings">
		<h1><?php esc_html_e( 'Booking Price Tiers', 'sev-calculate-price-for-booking-calendar' ); ?></h1>

		<?php if ( ! sevmatic_bcp_wpbc_is_available() ) : ?>
			<div class="notice notice-warning">
				<p>
					<?php esc_html_e( 'The "Booking Calendar" plugin is not active. Price calculation is currently inactive.', 'sev-calculate-price-for-booking-calendar' ); ?>
				</p>
			</div>
		<?php endif; ?>

		<p>
			<?php
			esc_html_e(
				'Define here how much a booking costs per day. The number of booked days determines which price tier applies, and the total is that tier\'s rate multiplied by the number of days. The calculated price is shown in the booking form via the [cost_hint] placeholder.',
				'sev-calculate-price-for-booking-calendar'
			);
			?>
		</p>

		<form method="post">
			<?php wp_nonce_field( SEVMATIC_BCP_NONCE_ACTION, SEVMATIC_BCP_NONCE_NAME ); ?>

			<h2><?php esc_html_e( 'Price Tiers', 'sev-calculate-price-for-booking-calendar' ); ?></h2>

			<table class="widefat sevmatic-bcp-tiers-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'From Day', 'sev-calculate-price-for-booking-calendar' ); ?></th>
						<th><?php esc_html_e( 'To Day', 'sev-calculate-price-for-booking-calendar' ); ?></th>
						<th><?php esc_html_e( 'Price per Day', 'sev-calculate-price-for-booking-calendar' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody id="sevmatic-bcp-tiers-rows">
					<?php foreach ( $settings['tiers'] as $index => $tier ) : ?>
						<?php sevmatic_bcp_render_tier_row( $index, $tier ); ?>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p>
				<button type="button" class="button" id="sevmatic-bcp-add-row">
					<?php esc_html_e( 'Add price tier', 'sev-calculate-price-for-booking-calendar' ); ?>
				</button>
			</p>

			<p class="description">
				<?php esc_html_e( 'Leave "To Day" empty for "unlimited" (e.g. "20 days or more").', 'sev-calculate-price-for-booking-calendar' ); ?>
			</p>

			<h2><?php esc_html_e( 'Price Display', 'sev-calculate-price-for-booking-calendar' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="sevmatic-bcp-decimals"><?php esc_html_e( 'Decimals', 'sev-calculate-price-for-booking-calendar' ); ?></label>
					</th>
					<td>
						<input type="number" min="0" max="4" step="1" id="sevmatic-bcp-decimals"
							   name="sevmatic_bcp[decimals]" value="<?php echo esc_attr( (string) $settings['decimals'] ); ?>" class="small-text" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="sevmatic-bcp-decimal-sep"><?php esc_html_e( 'Decimal Separator', 'sev-calculate-price-for-booking-calendar' ); ?></label>
					</th>
					<td>
						<input type="text" id="sevmatic-bcp-decimal-sep" maxlength="1"
							   name="sevmatic_bcp[decimal_separator]" value="<?php echo esc_attr( $settings['decimal_separator'] ); ?>" class="small-text" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="sevmatic-bcp-thousand-sep"><?php esc_html_e( 'Thousand Separator', 'sev-calculate-price-for-booking-calendar' ); ?></label>
					</th>
					<td>
						<input type="text" id="sevmatic-bcp-thousand-sep" maxlength="1"
							   name="sevmatic_bcp[thousand_separator]" value="<?php echo esc_attr( $settings['thousand_separator'] ); ?>" class="small-text" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="sevmatic-bcp-prefix"><?php esc_html_e( 'Prefix', 'sev-calculate-price-for-booking-calendar' ); ?></label>
					</th>
					<td>
						<input type="text" id="sevmatic-bcp-prefix"
							   name="sevmatic_bcp[prefix]" value="<?php echo esc_attr( $settings['prefix'] ); ?>" class="regular-text" placeholder="e.g. &euro;&nbsp;" />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="sevmatic-bcp-suffix"><?php esc_html_e( 'Suffix', 'sev-calculate-price-for-booking-calendar' ); ?></label>
					</th>
					<td>
						<input type="text" id="sevmatic-bcp-suffix"
							   name="sevmatic_bcp[suffix]" value="<?php echo esc_attr( $settings['suffix'] ); ?>" class="regular-text" placeholder="e.g. &nbsp;&euro;" />
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Save', 'sev-calculate-price-for-booking-calendar' ), 'primary', 'sevmatic_bcp_save' ); ?>
		</form>

		<template id="sevmatic-bcp-row-template">
			<?php sevmatic_bcp_render_tier_row( 0 ); ?>
		</template>
	</div>
	<?php
}
