=== SEV Calculate Price for Booking Calendar ===
Contributors: hfranz
Tags: booking calendar, wpbc, price, pricing, calculator
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Calculates and displays the booking price and deposit for Booking Calendar based on configurable per-day price tiers.

== Description ==

The free version of the "Booking Calendar" plugin (WPBC) ships `[cost_hint]` and `[deposit_hint]` fields in its form builder, but the actual calculation behind them is a paid "Business Medium+" feature. On the free version, these shortcodes in a booking form are never replaced and show up literally on the frontend.

SEV Calculate Price for Booking Calendar fills exactly that gap:

* It calculates a price from the number of selected booking days and configurable day-range price tiers (e.g. 1-9 days at one rate, 10-20 days at another, 20+ days at a third), and displays it wherever `[cost_hint]` is used in a Booking Calendar form.
* It optionally calculates a deposit — either a fixed amount or a percentage of the calculated price — and displays it wherever `[deposit_hint]` is used.

Both update live as the visitor picks dates, the same way Booking Calendar's own `[days_number_hint]` already does.

**How it works**

* Whichever price tier covers the total number of selected days applies to *all* of those days (a flat per-day rate for that block), not a graduated/progressive rate.
* Price tiers, the deposit, and the price display format (decimals, decimal/thousand separator, prefix/suffix) are configured under Settings > Booking Price Tiers.
* Integrates with Booking Calendar's existing AJAX cost-hint round-trip and its own `wpbc_free_date_hints__apply()` helper — no changes to the booking form template are required beyond having `[cost_hint]` and/or `[deposit_hint]` in it.

**Requirements**

Requires the "Booking Calendar" plugin (WPBC) to be active. Without it, this plugin does nothing.

== Installation ==

1. Upload the `sev-calculate-price-for-booking-calendar` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Go to Settings > Booking Price Tiers and configure your day-range price tiers and (optionally) the deposit.
4. Make sure the booking form (Booking Calendar form builder) contains the `[cost_hint]` and/or `[deposit_hint]` field/shortcode.

== Frequently Asked Questions ==

= Does this replace Booking Calendar's paid pricing add-on? =

No — it's a minimal replacement for the specific case of a flat per-day rate with day-count ranges, plus a simple fixed/percentage deposit. Booking Calendar's paid versions offer far more (seasonal prices, per-resource pricing, additional costs, etc.).

= Can different booking resources ("types") have different price tiers? =

Not currently — one set of price tiers (and one deposit setting) applies to all resources.

= How is the deposit calculated? =

Either as a fixed amount, or as a percentage of the calculated total price — configurable under Settings > Booking Price Tiers.

== Changelog ==

= 1.1.0 =
* Added: deposit calculation (fixed amount or percentage of the price), shown via the `[deposit_hint]` placeholder.

= 1.0.0 =
* Initial release.
