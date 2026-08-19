<?php
/**
 * Settings storage: price tiers and price-display format, kept in a single
 * option.
 *
 * @package sevmatic
 */

defined( 'ABSPATH' ) || exit;

define( 'SEVMATIC_BCP_OPTION_KEY', 'sevmatic_bcp_settings' );

/**
 * Default settings, used until the admin configures their own tiers.
 *
 * @return array{tiers: array<int, array{from:int,to:?int,price:float}>, decimals:int, decimal_separator:string, thousand_separator:string, prefix:string, suffix:string}
 */
function sevmatic_bcp_get_default_settings(): array {

	return array(
		'tiers'              => array(
			array(
				'from'  => 1,
				'to'    => 9,
				'price' => 0.0,
			),
		),
		'decimals'           => 2,
		'decimal_separator'  => ',',
		'thousand_separator' => '.',
		'prefix'             => '',
		'suffix'             => '',
	);
}

/**
 * Reads the stored settings, merged over the defaults.
 *
 * @return array{tiers: array<int, array{from:int,to:?int,price:float}>, decimals:int, decimal_separator:string, thousand_separator:string, prefix:string, suffix:string}
 */
function sevmatic_bcp_get_settings(): array {

	$stored = get_option( SEVMATIC_BCP_OPTION_KEY, array() );

	if ( ! is_array( $stored ) ) {
		$stored = array();
	}

	return wp_parse_args( $stored, sevmatic_bcp_get_default_settings() );
}

/**
 * Sanitizes a short display string (price prefix/suffix) for safe output.
 *
 * Deliberately doesn't use sanitize_text_field()/wp_strip_all_tags(): both
 * trim() the result, which would silently swallow an intentional leading or
 * trailing space (e.g. a " Euro" suffix so "30,00" + " Euro" doesn't run
 * together as "30,00Euro").
 *
 * @param string $value Raw value.
 *
 * @return string
 */
function sevmatic_bcp_sanitize_display_string( string $value ): string {

	$value = wp_check_invalid_utf8( $value );
	$value = strip_tags( $value );

	// Strip control characters (line breaks, tabs, etc.), but keep normal spaces.
	return (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value );
}

/**
 * Sanitizes and normalizes settings coming from the admin settings form.
 *
 * Invalid tier rows (missing/non-numeric "from" or "price") are dropped.
 * Valid rows are sorted by their "from" day, ascending, so
 * sevmatic_bcp_find_tier_for_days() can rely on encountering the lowest
 * matching tier first.
 *
 * @param array $raw Raw, unsanitized settings (typically from $_POST).
 *
 * @return array{tiers: array<int, array{from:int,to:?int,price:float}>, decimals:int, decimal_separator:string, thousand_separator:string, prefix:string, suffix:string}
 */
function sevmatic_bcp_sanitize_settings( array $raw ): array {

	$defaults = sevmatic_bcp_get_default_settings();

	$tiers     = array();
	$raw_tiers = isset( $raw['tiers'] ) && is_array( $raw['tiers'] ) ? $raw['tiers'] : array();

	foreach ( $raw_tiers as $raw_tier ) {
		if ( ! is_array( $raw_tier ) ) {
			continue;
		}

		$from_raw  = isset( $raw_tier['from'] ) ? trim( (string) $raw_tier['from'] ) : '';
		$to_raw    = isset( $raw_tier['to'] ) ? trim( (string) $raw_tier['to'] ) : '';
		$price_raw = isset( $raw_tier['price'] ) ? trim( (string) $raw_tier['price'] ) : '';

		if ( '' === $from_raw || ! is_numeric( $from_raw ) || '' === $price_raw || ! is_numeric( $price_raw ) ) {
			continue;
		}

		$from  = max( 1, (int) $from_raw );
		$price = max( 0.0, (float) $price_raw );
		$to    = ( '' !== $to_raw && is_numeric( $to_raw ) ) ? max( $from, (int) $to_raw ) : null;

		$tiers[] = array(
			'from'  => $from,
			'to'    => $to,
			'price' => $price,
		);
	}

	usort(
		$tiers,
		function ( array $a, array $b ): int {
			return $a['from'] <=> $b['from'];
		}
	);

	if ( empty( $tiers ) ) {
		$tiers = $defaults['tiers'];
	}

	$decimals = isset( $raw['decimals'] ) && is_numeric( $raw['decimals'] ) ? max( 0, min( 4, (int) $raw['decimals'] ) ) : $defaults['decimals'];

	return array(
		'tiers'              => $tiers,
		'decimals'           => $decimals,
		'decimal_separator'  => isset( $raw['decimal_separator'] ) ? sanitize_text_field( (string) $raw['decimal_separator'] ) : $defaults['decimal_separator'],
		'thousand_separator' => isset( $raw['thousand_separator'] ) ? sanitize_text_field( (string) $raw['thousand_separator'] ) : $defaults['thousand_separator'],
		'prefix'             => isset( $raw['prefix'] ) ? sevmatic_bcp_sanitize_display_string( (string) $raw['prefix'] ) : $defaults['prefix'],
		'suffix'             => isset( $raw['suffix'] ) ? sevmatic_bcp_sanitize_display_string( (string) $raw['suffix'] ) : $defaults['suffix'],
	);
}
