<?php
/**
 * Pure price-calculation helpers.
 *
 * Deliberately free of any WordPress or Booking Calendar function calls, so
 * they can be unit-tested directly and reused from both the WPBC AJAX
 * handler and the admin settings preview.
 *
 * @package sevmatic
 */

defined( 'ABSPATH' ) || exit;

/**
 * Finds the price tier that applies to a given number of days.
 *
 * Tiers are "block" rates, not progressive/graduated ones: whichever tier
 * the total day count falls into determines the per-day price for *all*
 * of those days (e.g. 12 days at the "10-20 days" rate), matching how the
 * car-rental style range pricing was described ("ab 10-20 Tage X pro Tag").
 *
 * @param array<int, array{from:int,to:?int,price:float}> $tiers Price tiers. `to` of null means unbounded.
 * @param int                                              $days  Number of booking days (>= 1).
 *
 * @return array{from:int,to:?int,price:float}|null The matching tier, or null if none matches.
 */
function sevmatic_bcp_find_tier_for_days( array $tiers, int $days ): ?array {

	foreach ( $tiers as $tier ) {
		$from = (int) $tier['from'];
		$to   = isset( $tier['to'] ) ? $tier['to'] : null;

		if ( $days < $from ) {
			continue;
		}

		if ( null !== $to && $days > (int) $to ) {
			continue;
		}

		return $tier;
	}

	return null;
}

/**
 * Calculates the total price for a number of booking days.
 *
 * @param array<int, array{from:int,to:?int,price:float}> $tiers Price tiers, as accepted by sevmatic_bcp_find_tier_for_days().
 * @param int                                              $days  Number of booking days.
 *
 * @return float|null Total price, or null if no tier covers this day count.
 */
function sevmatic_bcp_calculate_total_price( array $tiers, int $days ): ?float {

	if ( $days <= 0 ) {
		return null;
	}

	$tier = sevmatic_bcp_find_tier_for_days( $tiers, $days );

	if ( null === $tier ) {
		return null;
	}

	return $days * (float) $tier['price'];
}

/**
 * Calculates the deposit amount for a booking.
 *
 * @param float|null $total_price Total booking price, as returned by sevmatic_bcp_calculate_total_price() (or null if it couldn't be calculated).
 * @param string     $mode        'fixed' for a flat amount, 'percentage' for a share of $total_price.
 * @param float      $value       The fixed amount, or the percentage (0-100).
 *
 * @return float|null Deposit amount, or null when it can't be determined (percentage mode without a total price, or an unknown mode).
 */
function sevmatic_bcp_calculate_deposit( ?float $total_price, string $mode, float $value ): ?float {

	if ( 'fixed' === $mode ) {
		return max( 0.0, $value );
	}

	if ( 'percentage' === $mode ) {
		if ( null === $total_price ) {
			return null;
		}

		return $total_price * $value / 100;
	}

	return null;
}

/**
 * Formats a price amount for display.
 *
 * @param float                                                                            $amount Amount to format.
 * @param array{decimals:int,decimal_separator:string,thousand_separator:string,prefix:string,suffix:string} $format Formatting options.
 *
 * @return string
 */
function sevmatic_bcp_format_price( float $amount, array $format ): string {

	$number = number_format(
		$amount,
		(int) $format['decimals'],
		(string) $format['decimal_separator'],
		(string) $format['thousand_separator']
	);

	return $format['prefix'] . $number . $format['suffix'];
}
