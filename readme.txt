=== SEV Calculate Price for Booking Calendar ===
Contributors: hfranz
Tags: booking calendar, wpbc, price, pricing, calculator
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Calculates and displays the booking price for Booking Calendar based on configurable per-day price tiers.

== Description ==

The free version of the "Booking Calendar" plugin (WPBC) ships a `[cost_hint]` field in its form builder, but the actual price calculation behind it is a paid "Business Medium+" feature. On the free version, `[cost_hint]` in a booking form is never replaced and shows up literally on the frontend.

SEV Calculate Price for Booking Calendar fills exactly that gap: it calculates a price from the number of selected booking days and configurable day-range price tiers (e.g. 1-9 days at one rate, 10-20 days at another, 20+ days at a third), and displays it wherever `[cost_hint]` is used in a Booking Calendar form — updating live as the visitor picks dates, the same way Booking Calendar's own `[days_number_hint]` already does.

**How it works**

* Whichever price tier covers the total number of selected days applies to *all* of those days (a flat per-day rate for that block), not a graduated/progressive rate.
* Price tiers and the price display format (decimals, decimal/thousand separator, prefix/suffix) are configured under Settings > Booking Preisstaffel.
* Integrates with Booking Calendar's existing AJAX cost-hint round-trip and its own `wpbc_free_date_hints__apply()` helper — no changes to the booking form template are required beyond having `[cost_hint]` in it.

**Requirements**

Requires the "Booking Calendar" plugin (WPBC) to be active. Without it, this plugin does nothing.

== Installation ==

1. Upload the `sev-calculate-price-for-booking-calendar` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Go to Settings > Booking Preisstaffel and configure your day-range price tiers.
4. Make sure the booking form (Booking Calendar form builder) contains the `[cost_hint]` field/shortcode.

== Frequently Asked Questions ==

= Does this replace Booking Calendar's paid pricing add-on? =

No — it's a minimal replacement for the specific case of a flat per-day rate with day-count ranges. Booking Calendar's paid versions offer far more (seasonal prices, per-resource pricing, additional costs, deposits, etc.).

= Can different booking resources ("types") have different price tiers? =

Not currently — one set of price tiers applies to all resources.

== Changelog ==

= 1.0.0 =
* Initial release.
