<?php
/**
 * Booking Calendar (WPBC) integration.
 *
 * Booking Calendar Free ships a "Cost Hint" form field ([cost_hint]) in its
 * form builder, but the actual per-day price calculation behind it is a
 * paid "Business Medium+" feature (see
 * includes/page-form-builder/field-packs/hint-cost_hint/ and
 * includes/_front_end/date-hints.php in the Booking Calendar plugin: its
 * WPBC_Free_Date_Hints class explicitly does not compute a cost hint value).
 * On the Free version, [cost_hint] in a booking form is therefore never
 * replaced and shows up literally.
 *
 * This backfills exactly that gap using the same mechanism Booking Calendar
 * itself uses for its free date hints (days_number_hint & co.):
 *   - the [cost_hint] shortcode in the rendered form is replaced with a
 *     placeholder <span>/<input>, keyed by the current booking resource,
 *   - once dates are selected, Booking Calendar's own AJAX round-trip
 *     ("wpdev_ajax_show_cost" / action=CALCULATE_THE_COST") is reused to
 *     recalculate and push the value into that placeholder, via Booking
 *     Calendar's own wpbc_free_date_hints__apply() JS helper.
 *
 * @package sevmatic
 */

defined( 'ABSPATH' ) || exit;

const SEVMATIC_BCP_HINT_TOKEN = 'cost_hint';

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

		add_bk_filter( 'wpbc_update_bookingform_content__after_load', 'sevmatic_bcp_replace_cost_hint_in_form' );
		add_filter( 'wpbc_booking_form_content__after_load', 'sevmatic_bcp_replace_cost_hint_in_form', 10, 3 );

		add_bk_action( 'wpdev_ajax_show_cost', 'sevmatic_bcp_ajax_show_price' );
	},
	20
);

/**
 * Replaces the [cost_hint] shortcode in a rendered booking form with a
 * placeholder span/hidden-input pair, following the exact same markup
 * convention Booking Calendar's own free date hints use, so the existing
 * `wpbc_free_date_hints__apply()` JS helper (and its `#cost_hint_tip{id}`
 * selectors) picks it up without any JS of our own.
 *
 * @param string $form_content Booking form markup.
 * @param int    $resource_id  Booking resource ("type") ID.
 * @param string $form_slug    Booking form slug (unused, kept for filter signature parity).
 *
 * @return string
 */
function sevmatic_bcp_replace_cost_hint_in_form( string $form_content, int $resource_id = 1, string $form_slug = 'standard' ): string {

	if ( false === strpos( $form_content, '[' . SEVMATIC_BCP_HINT_TOKEN . ']' ) ) {
		return $form_content;
	}

	$resource_id = (int) $resource_id;
	$span_class  = SEVMATIC_BCP_HINT_TOKEN . '_tip' . $resource_id;
	$input_name  = SEVMATIC_BCP_HINT_TOKEN . $resource_id;
	$default     = '...';

	$first_html = '<span class="wpbc_field_hint wpbc_free_date_hint" id="' . esc_attr( $span_class ) . '">' . esc_html( $default ) . '</span>'
		. '<input class="wpbc_field_hint wpbc_free_date_hint" id="' . esc_attr( $input_name ) . '" name="' . esc_attr( $input_name ) . '" value="' . esc_attr( $default ) . '" style="display:none;" type="text" />';

	$pattern      = '/\[' . preg_quote( SEVMATIC_BCP_HINT_TOKEN, '/' ) . '\]/';
	$form_content = preg_replace( $pattern, $first_html, $form_content, 1 );

	$other_html = '<span class="wpbc_field_hint wpbc_free_date_hint ' . esc_attr( $span_class ) . '">' . esc_html( $default ) . '</span>';

	return str_replace( '[' . SEVMATIC_BCP_HINT_TOKEN . ']', $other_html, $form_content );
}

/**
 * Hooked into Booking Calendar's "wpdev_ajax_show_cost" action, which runs
 * on every "CALCULATE_THE_COST" AJAX request Booking Calendar's own
 * JS (wpbc-free-date-hints.js) already sends whenever the selected dates
 * or form fields change. Reads the same $_POST payload Booking Calendar's
 * own handler reads, calculates the price, and prints a small script that
 * pushes it into the placeholder from sevmatic_bcp_replace_cost_hint_in_form().
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

	// Nothing selected yet: leave whatever the placeholder currently shows (its initial "..." default) alone.
	if ( ! sevmatic_bcp_has_date_selection( $selected_dates ) ) {
		return;
	}

	sevmatic_bcp_print_price_update_script( $resource_id, sevmatic_bcp_calculate_formatted_price_for_selection( $selected_dates, $resource_id, $form_data ) );
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
 * Calculates and formats the price for a Booking Calendar date selection.
 *
 * Once dates *are* selected, this always returns a display string — an
 * empty one when the day count isn't covered by any configured price tier —
 * so the placeholder never keeps showing a stale price from a previous,
 * now-superseded selection.
 *
 * @param string $selected_dates Selected dates, in Booking Calendar's dd.mm.yyyy CSV/range format.
 * @param int    $resource_id    Booking resource ID.
 * @param string $form_data      Serialized booking form data (passed through to Booking Calendar's own parser).
 *
 * @return string Formatted price, or an empty string when it can't be calculated.
 */
function sevmatic_bcp_calculate_formatted_price_for_selection( string $selected_dates, int $resource_id, string $form_data ): string {

	$dates_in_diff_formats = wpbc_get_dates_in_diff_formats( trim( $selected_dates ), $resource_id, $form_data );

	if ( empty( $dates_in_diff_formats['array'] ) || ! is_array( $dates_in_diff_formats['array'] ) ) {
		return '';
	}

	$days = count( array_values( array_unique( array_filter( $dates_in_diff_formats['array'] ) ) ) );

	if ( $days <= 0 ) {
		return '';
	}

	$settings = sevmatic_bcp_get_settings();
	$total    = sevmatic_bcp_calculate_total_price( $settings['tiers'], $days );

	if ( null === $total ) {
		return '';
	}

	return sevmatic_bcp_format_price( $total, $settings );
}

/**
 * Prints the JS snippet that applies the calculated price to the
 * placeholder, reusing Booking Calendar's own wpbc_free_date_hints__apply()
 * helper when present (same helper WPBC_Free_Date_Hints relies on), with a
 * plain-jQuery fallback matching Booking Calendar's own fallback shape.
 *
 * @param int    $resource_id      Booking resource ID.
 * @param string $formatted_price  Already-formatted price string.
 *
 * @return void
 */
function sevmatic_bcp_print_price_update_script( int $resource_id, string $formatted_price ): void {
	?>
	<script type="text/javascript">
		(function ( w, $ ) {
			var resourceId = <?php echo wp_json_encode( $resource_id ); ?>;
			var hints = <?php echo wp_json_encode( array( SEVMATIC_BCP_HINT_TOKEN => $formatted_price ) ); ?>;

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
