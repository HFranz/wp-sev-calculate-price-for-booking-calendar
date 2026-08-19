<?php /** @noinspection PhpUnused */

declare( strict_types=1 );

/*
 * Minimal WordPress stubs for PHPUnit tests without a full WP bootstrap.
 * Only what includes/price-calculator.php and includes/settings.php
 * actually call is provided, following the same approach as
 * sev-simple-hreflang/tests/bootstrap.php.
 */

define( 'ABSPATH', dirname( __DIR__, 3 ) . '/' );

// ---------------------------------------------------------------------------
// WP function stubs
// ---------------------------------------------------------------------------

function sanitize_text_field( string $value ): string {
	return trim( $value );
}

function wp_check_invalid_utf8( string $value ): string {
	return $value;
}

function wp_parse_args( array $args, array $defaults ): array {
	return array_merge( $defaults, $args );
}

function get_option( string $name, mixed $default = false ): mixed {
	return Fixtures::$options[ $name ] ?? $default;
}

function update_option( string $name, mixed $value ): bool {
	Fixtures::$options[ $name ] = $value;
	return true;
}

// ---------------------------------------------------------------------------
// Configurable fixture store, reset before each test
// ---------------------------------------------------------------------------

class Fixtures {
	/** @var array<string, mixed> get_option()/update_option() store. */
	public static array $options = array();

	public static function reset(): void {
		self::$options = array();
	}
}

// ---------------------------------------------------------------------------
// Load the plugin's includes (pure functions + option handling; the WPBC
// integration and admin-settings files are intentionally not loaded here,
// since they pull in the much larger Booking Calendar/wp-admin surface and
// are exercised through manual/integration testing instead)
// ---------------------------------------------------------------------------

require_once dirname( __DIR__ ) . '/includes/price-calculator.php';
require_once dirname( __DIR__ ) . '/includes/settings.php';
