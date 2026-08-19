<?php

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

final class PriceCalculatorTest extends TestCase {

	/**
	 * @var array<int, array{from:int,to:?int,price:float}>
	 */
	private array $tiers;

	protected function setUp(): void {
		// 1-9 days: 12/day, 10-20 days: 10/day, 21+ days: 8/day.
		$this->tiers = array(
			array(
				'from'  => 1,
				'to'    => 9,
				'price' => 12.0,
			),
			array(
				'from'  => 10,
				'to'    => 20,
				'price' => 10.0,
			),
			array(
				'from'  => 21,
				'to'    => null,
				'price' => 8.0,
			),
		);
	}

	public function test_finds_the_tier_covering_the_lower_bound_of_a_range(): void {
		$tier = sevmatic_bcp_find_tier_for_days( $this->tiers, 1 );

		$this->assertSame( 12.0, $tier['price'] );
	}

	public function test_finds_the_tier_covering_the_upper_bound_of_a_range(): void {
		$tier = sevmatic_bcp_find_tier_for_days( $this->tiers, 9 );

		$this->assertSame( 12.0, $tier['price'] );
	}

	public function test_finds_the_next_tier_right_after_a_boundary(): void {
		$tier = sevmatic_bcp_find_tier_for_days( $this->tiers, 10 );

		$this->assertSame( 10.0, $tier['price'] );
	}

	public function test_open_ended_tier_matches_any_day_count_above_its_from(): void {
		$tier = sevmatic_bcp_find_tier_for_days( $this->tiers, 365 );

		$this->assertSame( 8.0, $tier['price'] );
	}

	public function test_returns_null_when_no_tier_covers_the_day_count(): void {
		$tiers = array(
			array(
				'from'  => 5,
				'to'    => 10,
				'price' => 12.0,
			),
		);

		$this->assertNull( sevmatic_bcp_find_tier_for_days( $tiers, 2 ) );
	}

	public function test_total_price_multiplies_days_by_the_matching_tiers_per_day_rate(): void {
		// 4 days falls into the 1-9 day tier: 4 * 12/day = 48.00.
		$this->assertSame( 48.0, sevmatic_bcp_calculate_total_price( $this->tiers, 4 ) );
	}

	public function test_total_price_uses_the_flat_block_rate_not_a_graduated_one(): void {
		// 12 days falls into the 10-20 tier: ALL 12 days bill at 10/day (120),
		// not a mix of the 1-9 and 10-20 rates.
		$this->assertSame( 120.0, sevmatic_bcp_calculate_total_price( $this->tiers, 12 ) );
	}

	public function test_total_price_is_null_for_zero_or_negative_days(): void {
		$this->assertNull( sevmatic_bcp_calculate_total_price( $this->tiers, 0 ) );
		$this->assertNull( sevmatic_bcp_calculate_total_price( $this->tiers, -1 ) );
	}

	public function test_total_price_is_null_when_no_tier_covers_the_day_count(): void {
		$tiers = array(
			array(
				'from'  => 5,
				'to'    => 10,
				'price' => 12.0,
			),
		);

		$this->assertNull( sevmatic_bcp_calculate_total_price( $tiers, 2 ) );
	}

	public function test_deposit_fixed_mode_ignores_the_total_price(): void {
		$this->assertSame( 50.0, sevmatic_bcp_calculate_deposit( 40.0, 'fixed', 50.0 ) );
		$this->assertSame( 50.0, sevmatic_bcp_calculate_deposit( null, 'fixed', 50.0 ) );
	}

	public function test_deposit_percentage_mode_is_a_share_of_the_total_price(): void {
		$this->assertSame( 8.0, sevmatic_bcp_calculate_deposit( 40.0, 'percentage', 20.0 ) );
	}

	public function test_deposit_percentage_mode_is_null_without_a_total_price(): void {
		$this->assertNull( sevmatic_bcp_calculate_deposit( null, 'percentage', 20.0 ) );
	}

	public function test_deposit_is_null_for_an_unknown_mode(): void {
		$this->assertNull( sevmatic_bcp_calculate_deposit( 40.0, 'bogus', 20.0 ) );
	}

	public function test_deposit_fixed_mode_clamps_negative_values_to_zero(): void {
		$this->assertSame( 0.0, sevmatic_bcp_calculate_deposit( 40.0, 'fixed', -5.0 ) );
	}

	public function test_format_price_uses_german_style_separators_by_default(): void {
		$format = array(
			'decimals'           => 2,
			'decimal_separator'  => ',',
			'thousand_separator' => '.',
			'prefix'             => '',
			'suffix'             => '',
		);

		$this->assertSame( '40,00', sevmatic_bcp_format_price( 40.0, $format ) );
		$this->assertSame( '1.234,50', sevmatic_bcp_format_price( 1234.5, $format ) );
	}

	public function test_format_price_applies_prefix_and_suffix(): void {
		$format = array(
			'decimals'           => 2,
			'decimal_separator'  => ',',
			'thousand_separator' => '.',
			'prefix'             => '€ ',
			'suffix'             => '',
		);

		$this->assertSame( '€ 40,00', sevmatic_bcp_format_price( 40.0, $format ) );
	}
}
