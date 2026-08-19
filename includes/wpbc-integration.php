<?php
/**
 * Booking Calendar (WPBC) integration.
 *
 * Booking Calendar Free ships "Cost Hint" and "Deposit Hint" form fields
 * ([cost_hint], [deposit_hint]) in its form builder, but the actual price
 * calculation behind them is a paid "Business Medium+" feature (see
 * includes/page-form-builder/field-packs/hint-cost_hint/,
 * hint-deposit_hint/ and includes/_front_end/date-hints.php in the Booking
 * Calendar plugin: its WPBC_Free_Date_Hints class explicitly does not
 * compute either value). On the Free version, these shortcodes in a
 * booking form are therefore never replaced and show up literally.
 *
 * This backfills exactly that gap using the same mechanism Booking Calendar
 * itself uses for its free date hints (days_number_hint & co.):
 *   - each shortcode in the rendered form is replaced with a placeholder
 *     <span>/<input>, keyed by the current booking resource,
 *   - once dates are selected, Booking Calendar's own AJAX round-trip
 *     ("wpdev_ajax_show_cost" / action=CALCULATE_THE_COST") is reused to
 *     recalculate and push the values into those placeholders, via Booking
 *     Calendar's own wpbc_free_date_hints__apply() JS helper.
 *
 * @package sevmatic
 */

defined( 'ABSPATH' ) || exit;

const SEVMATIC_BCP_COST_HINT_TOKEN    = 'cost_hint';
const SEVMATIC_BCP_DEPOSIT_HINT_TOKEN = 'deposit_hint';

/**
 * Whether Booking Calendar's hook system (needed by everything below) is
 * loaded and active.
 *
 * @return bool
 */
function sevmatic_bcp_wpbc_is_available(): bool {

	return function_exists( 'add_bk_action' )
		&& function_exists( 'add_bk_filter' )
		&& function_exists( 'wpbc_get_dates_in_diff_formats' );
}

add_action(
	'plugins_loaded',
	function () {
		if ( ! sevmatic_bcp_wpbc_is_available() ) {
			return;
		}

		add_bk_filter( 'wpbc_update_bookingform_content__after_load', 'sevmatic_bcp_replace_price_hints_in_form' );
		add_filter( 'wpbc_booking_form_content__after_load', 'sevmatic_bcp_replace_price_hints_in_form', 10, 3 );

		add_bk_action( 'wpdev_ajax_show_cost', 'sevmatic_bcp_ajax_show_price' );
	},
	20
);

/**
 * Replaces the [cost_hint] and [deposit_hint] shortcodes in a rendered
 * booking form with placeholder span/hidden-input pairs.
 *
 * @param string $form_content Booking form markup.
 * @param int    $resource_id  Booking resource ("type") ID.
 * @param string $form_slug    Booking form slug (unused, kept for filter signature parity).
 *
 * @return string
 */
function sevmatic_bcp_replace_price_hints_in_form( string $form_content, int $resource_id = 1, string $form_slug = 'standard' ): string {

	foreach ( array( SEVMATIC_BCP_COST_HINT_TOKEN, SEVMATIC_BCP_DEPOSIT_HINT_TOKEN ) as $token ) {
		$form_content = sevmatic_bcp_replace_hint_token_in_form( $form_content, $token, (int) $resource_id );
	}

	return $form_content;
}

/**
 * Replaces a single hint shortcode (e.g. [cost_hint]) with a placeholder
 * span/hidden-input pair, following the exact same markup convention
 * Booking Calendar's own free date hints use, so the existing
 * `wpbc_free_date_hints__apply()` JS helper (and its `#{token}_tip{id}`
 * selectors) picks it up without any JS of our own.
 *
 * @param string $form_content Booking form markup.
 * @param string $token        Hint token, e.g. "cost_hint" (without brackets).
 * @param int    $resource_id  Booking resource ("type") ID.
 *
 * @return string
 */
function sevmatic_bcp_replace_hint_token_in_form( string $form_content, string $token, int $resource_id ): string {

	if ( false === strpos( $form_content, '[' . $token . ']' ) ) {
		return $form_content;
	}

	$span_class = $token . '_tip' . $resource_id;
	$input_name = $token . $resource_id;
	$default    = '...';

	$first_html = '<span class="wpbc_field_hint wpbc_free_date_hint" id="' . esc_attr( $span_class ) . '">' . esc_html( $default ) . '</span>'
		. '<input class="wpbc_field_hint wpbc_free_date_hint" id="' . esc_attr( $input_name ) . '" name="' . esc_attr( $input_name ) . '" value="' . esc_attr( $default ) . '" style="display:none;" type="text" />';

	$pattern      = '/\[' . preg_quote( $token, '/' ) . '\]/';
	$form_content = preg_replace( $pattern, $first_html, $form_content, 1 );

	$other_html = '<span class="wpbc_field_hint wpbc_free_date_hint ' . esc_attr( $span_class ) . '">' . esc_html( $default ) . '</span>';

	return str_replace( '[' . $token . ']', $other_html, $form_content );
}

/**
 * Hooked into Booking Calendar's "wpdev_ajax_show_cost" action, which runs
 * on every "CALCULATE_THE_COST" AJAX request Booking Calendar's own
 * JS (wpbc-free-date-hints.js) already sends whenever the selected dates
 * or form fields change. Reads the same $_POST payload Booking Calendar's
 * own handler reads, calculates price and deposit, and prints a small
 * script that pushes them into the placeholders from
 * sevmatic_bcp_replace_hint_token_in_form().
 *
 * @return void
 */
function sevmatic_bcp_ajax_show_price(): void {

	$default_resource_id = 1;
	if ( class_exists( 'WPBC_FE_Attr_Postprocessor' ) && method_exists( 'WPBC_FE_Attr_Postprocessor', 'get_default_booking_resource_id' ) ) {
		$default_resource_id = WPBC_FE_Attr_Postprocessor::get_default_booking_resource_id();
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- Booking Calendar core already verified its own AJAX nonce before dispatching this action.
	$resource_id = isset( $_POST['bk_type'] ) ? (int) $_POST['bk_type'] : $default_resource_id;

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
	$selected_dates = isset( $_POST['all_dates'] ) ? sanitize_text_field( wp_unslash( $_POST['all_dates'] ) ) : '';

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
	$form_data = isset( $_POST['form'] ) ? sanitize_text_field( wp_unslash( $_POST['form'] ) ) : '';

	// Nothing selected yet: leave whatever the placeholders currently show (their initial "..." default) alone.
	if ( ! sevmatic_bcp_has_date_selection( $selected_dates ) ) {
		return;
	}

	$days = sevmatic_bcp_count_selected_days( $selected_dates, $resource_id, $form_data );

	if ( null === $days ) {
		return;
	}

	$settings = sevmatic_bcp_get_settings();
	$total    = sevmatic_bcp_calculate_total_price( $settings['tiers'], $days );
	$deposit  = sevmatic_bcp_calculate_deposit( $total, $settings['deposit_mode'], $settings['deposit_value'] );

	$hints = array(
		SEVMATIC_BCP_COST_HINT_TOKEN    => null !== $total ? sevmatic_bcp_format_price( $total, $settings ) : '',
		SEVMATIC_BCP_DEPOSIT_HINT_TOKEN => null !== $deposit ? sevmatic_bcp_format_price( $deposit, $settings ) : '',
	);

	sevmatic_bcp_print_price_update_script( $resource_id, $hints );
}

/**
 * Whether a Booking Calendar "all_dates" POST value represents an actual
 * date selection, as opposed to Booking Calendar's own "nothing selected
 * yet" sentinel.
 *
 * @param string $selected_dates Raw "all_dates" value.
 *
 * @return bool
 */
function sevmatic_bcp_has_date_selection( string $selected_dates ): bool {

	$selected_dates = trim( $selected_dates );

	// "13.01.2981" is Booking Calendar's own sentinel value for "nothing selected yet".
	return '' !== $selected_dates && false === strpos( $selected_dates, '13.01.2981' );
}

/**
 * Counts the number of selected booking days for a Booking Calendar date
 * selection, using Booking Calendar's own date parser.
 *
 * @param string $selected_dates Selected dates, in Booking Calendar's dd.mm.yyyy CSV/range format.
 * @param int    $resource_id    Booking resource ID.
 * @param string $form_data      Serialized booking form data (passed through to Booking Calendar's own parser).
 *
 * @return int|null Number of days, or null when it can't be determined.
 */
function sevmatic_bcp_count_selected_days( string $selected_dates, int $resource_id, string $form_data ): ?int {

	$dates_in_diff_formats = wpbc_get_dates_in_diff_formats( trim( $selected_dates ), $resource_id, $form_data );

	if ( empty( $dates_in_diff_formats['array'] ) || ! is_array( $dates_in_diff_formats['array'] ) ) {
		return null;
	}

	$days = count( array_values( array_unique( array_filter( $dates_in_diff_formats['array'] ) ) ) );

	return $days > 0 ? $days : null;
}

/**
 * Prints the JS snippet that applies the calculated hint values to their
 * placeholders, reusing Booking Calendar's own wpbc_free_date_hints__apply()
 * helper when present (same helper WPBC_Free_Date_Hints relies on), with a
 * plain-jQuery fallback matching Booking Calendar's own fallback shape.
 *
 * @param int                  $resource_id Booking resource ID.
 * @param array<string,string> $hints       Hint token => already-formatted value.
 *
 * @return void
 */
function sevmatic_bcp_print_price_update_script( int $resource_id, array $hints ): void {
	?>
	<script type="text/javascript">
		(function ( w, $ ) {
			var resourceId = <?php echo wp_json_encode( $resource_id ); ?>;
			var hints = <?php echo wp_json_encode( $hints ); ?>;

			if ( w.wpbc_free_date_hints__apply ) {
				w.wpbc_free_date_hints__apply( resourceId, hints );
				return;
			}

			$.each( hints, function ( hintName, hintValue ) {
				$( '#' + hintName + '_tip' + resourceId + ',.' + hintName + '_tip' + resourceId ).html( hintValue );
				$( '#' + hintName + resourceId ).val( $( '<div />' ).html( hintValue ).text() );
			} );
		})( window, jQuery );
	</script>
	<?php
}
