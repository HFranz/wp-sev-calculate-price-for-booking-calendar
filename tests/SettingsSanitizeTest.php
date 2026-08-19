<?php

declare( strict_types=1 );

use PHPUnit\Framework\TestCase;

final class SettingsSanitizeTest extends TestCase {

	protected function setUp(): void {
		Fixtures::reset();
	}

	public function test_sanitizes_and_sorts_valid_rows_by_from_day(): void {
		$raw = array(
			'tiers' => array(
				array(
					'from'  => '10',
					'to'    => '20',
					'price' => '10',
				),
				array(
					'from'  => '1',
					'to'    => '9',
					'price' => '12',
				),
			),
		);

		$settings = sevmatic_bcp_sanitize_settings( $raw );

		$this->assertSame(
			array(
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
			),
			$settings['tiers']
		);
	}

	public function test_empty_to_field_becomes_an_unbounded_tier(): void {
		$raw = array(
			'tiers' => array(
				array(
					'from'  => '21',
					'to'    => '',
					'price' => '8',
				),
			),
		);

		$settings = sevmatic_bcp_sanitize_settings( $raw );

		$this->assertNull( $settings['tiers'][0]['to'] );
	}

	public function test_drops_rows_missing_a_required_from_or_price(): void {
		$raw = array(
			'tiers' => array(
				array(
					'from'  => '',
					'to'    => '9',
					'price' => '12',
				),
				array(
					'from'  => '1',
					'to'    => '9',
					'price' => '',
				),
				array(
					'from'  => '1',
					'to'    => '9',
					'price' => '12',
				),
			),
		);

		$settings = sevmatic_bcp_sanitize_settings( $raw );

		$this->assertCount( 1, $settings['tiers'] );
	}

	public function test_falls_back_to_default_tiers_when_all_rows_are_invalid(): void {
		$raw = array(
			'tiers' => array(
				array(
					'from'  => '',
					'to'    => '',
					'price' => '',
				),
			),
		);

		$settings = sevmatic_bcp_sanitize_settings( $raw );

		$this->assertSame( sevmatic_bcp_get_default_settings()['tiers'], $settings['tiers'] );
	}

	public function test_negative_price_is_clamped_to_zero(): void {
		$raw = array(
			'tiers' => array(
				array(
					'from'  => '1',
					'to'    => '9',
					'price' => '-5',
				),
			),
		);

		$settings = sevmatic_bcp_sanitize_settings( $raw );

		$this->assertSame( 0.0, $settings['tiers'][0]['price'] );
	}

	public function test_decimals_is_clamped_between_zero_and_four(): void {
		$settings = sevmatic_bcp_sanitize_settings( array( 'decimals' => '99' ) );
		$this->assertSame( 4, $settings['decimals'] );

		$settings = sevmatic_bcp_sanitize_settings( array( 'decimals' => '-3' ) );
		$this->assertSame( 0, $settings['decimals'] );
	}

	public function test_deposit_mode_and_value_are_sanitized(): void {
		$settings = sevmatic_bcp_sanitize_settings(
			array(
				'deposit_mode'  => 'fixed',
				'deposit_value' => '25',
			)
		);

		$this->assertSame( 'fixed', $settings['deposit_mode'] );
		$this->assertSame( 25.0, $settings['deposit_value'] );
	}

	public function test_deposit_mode_falls_back_to_default_when_invalid(): void {
		$settings = sevmatic_bcp_sanitize_settings( array( 'deposit_mode' => 'not-a-real-mode' ) );

		$this->assertSame( sevmatic_bcp_get_default_settings()['deposit_mode'], $settings['deposit_mode'] );
	}

	public function test_deposit_percentage_is_clamped_to_100(): void {
		$settings = sevmatic_bcp_sanitize_settings(
			array(
				'deposit_mode'  => 'percentage',
				'deposit_value' => '150',
			)
		);

		$this->assertSame( 100.0, $settings['deposit_value'] );
	}

	public function test_deposit_fixed_amount_is_not_clamped_to_100(): void {
		$settings = sevmatic_bcp_sanitize_settings(
			array(
				'deposit_mode'  => 'fixed',
				'deposit_value' => '150',
			)
		);

		$this->assertSame( 150.0, $settings['deposit_value'] );
	}

	public function test_negative_deposit_value_is_clamped_to_zero(): void {
		$settings = sevmatic_bcp_sanitize_settings( array( 'deposit_value' => '-10' ) );

		$this->assertSame( 0.0, $settings['deposit_value'] );
	}

	public function test_prefix_and_suffix_preserve_a_leading_or_trailing_space(): void {
		// sanitize_text_field()/wp_strip_all_tags() would trim() this away,
		// silently running the number and suffix together (e.g. "30,00Euro").
		$settings = sevmatic_bcp_sanitize_settings(
			array(
				'prefix' => '€ ',
				'suffix' => ' Euro',
			)
		);

		$this->assertSame( '€ ', $settings['prefix'] );
		$this->assertSame( ' Euro', $settings['suffix'] );
	}

	public function test_prefix_and_suffix_strip_html_tags(): void {
		$settings = sevmatic_bcp_sanitize_settings(
			array(
				'suffix' => ' <script>alert(1)</script>Euro',
			)
		);

		$this->assertSame( ' alert(1)Euro', $settings['suffix'] );
	}

	public function test_get_settings_merges_stored_values_over_defaults(): void {
		Fixtures::$options[ SEVMATIC_BCP_OPTION_KEY ] = array(
			'prefix' => '€ ',
		);

		$settings = sevmatic_bcp_get_settings();

		$this->assertSame( '€ ', $settings['prefix'] );
		$this->assertSame( sevmatic_bcp_get_default_settings()['tiers'], $settings['tiers'] );
	}
}
