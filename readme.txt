=== GHL Contact Sync ===
Contributors: seamkt
Tags: forms, gohighlevel, crm, contacts, leads
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 2.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create reusable frontend forms, store submissions locally, sync contacts to GoHighLevel, and recover WooCommerce abandoned carts.

== Description ==

GHL Contact Sync lets WordPress administrators manage reusable forms and prepare contacts for GoHighLevel synchronization.

Version 1.1.0 adds an optional WooCommerce Abandoned Cart module. WordPress tracks cart state, recovery, and order conversion. GoHighLevel receives contact/cart custom fields and the configured abandoned cart tag, then sends recovery emails through GHL Workflows.

== Changelog ==

= 2.0.1 =
* Changed GHL contact sync to match existing contacts by exact email only, then update instead of failing on duplicate contacts.

= 2.0.0 =
* Improved WooCommerce abandoned cart recovery links, checkout identity handling, admin visibility, and frontend form submissions.
* Added manual cleanup actions for saved form submissions and abandoned cart records.

= 1.1.10 =
* Debounce duplicate abandoned cart background jobs triggered by overlapping checkout events.
* Clarify abandoned cart admin status columns and expected GHL tag state.
* Added a plugin Settings quick link and expanded the abandoned cart workflow guide.
* Added admin submission listing for frontend forms.

= 1.1.9 =
* Prevent repeated recovery link clicks from duplicating cart item quantities.

= 1.1.8 =
* Ensure checkout email capture updates the recovered cart record instead of the logged-in account session.

= 1.1.7 =
* Reuse unexpired abandoned cart recovery links across repeat abandonment cycles.
* Stop recovery links from working after the cart has been purchased.
* Keep checkout email as the cart owner when a logged-in account uses a different checkout email.

= 1.1.6 =
* Fixed checkout email priority so the billing/checkout email is used instead of the logged-in admin account email.
* Reset stored GHL contact IDs when a cart email changes so tags are applied to the correct contact.
* Added Classic Checkout and Checkout Blocks server-side identity capture hooks.
* Improved abandoned cart GHL sync payloads and tag handling for current API behavior.
* Added immediate due-cart processing for faster abandoned cart testing from the admin.

= 1.1.5 =
* Added an admin action to delete empty or expired abandoned cart test rows in a bounded batch.

= 1.1.4 =
* Prevent repeated empty WooCommerce cart events from creating duplicate expired cart rows.

= 1.1.3 =
* Allow abandoned cart timeout to be set as low as 1 minute for testing.

= 1.1.2 =
* Added detailed admin feedback when automatic GHL abandoned cart field creation fails.

= 1.1.1 =
* Added automatic creation of recommended GoHighLevel abandoned cart contact custom fields.
* Added automatic abandoned cart field mapping after field creation.
* Switched abandoned cart custom field fetch/create calls to the current Location Custom Fields API version.

= 1.1.0 =
* Added WooCommerce abandoned cart tracking with custom cart and sync log tables.
* Added checkout identity capture, recovery links, order lifecycle handling, cleanup jobs, and GHL cart sync.
* Added Abandoned Cart admin overview, carts list/detail, settings, custom field mapping, and sync logs.
* Added current GHL contact upsert, custom field fetch/create, and dedicated contact tag add/remove endpoints.

= 1.0.5 =
* Delay popup closing briefly after successful external form submission.

= 1.0.4 =
* Added a "popup" option to disable the popup upon successful submission.

= 1.0.3 =
* Added an external form option to close the matched popup container after successful submit.

= 1.0.2 =
* Hide "Submit Behavior" when "External existing form" is selected as the source.

= 1.0.1 =
* Added frontend form submission handling with AJAX.
* Added local submission storage before syncing to GoHighLevel.
* Added support for external existing forms using wrapper, field, and submit button selectors.
* Added success/error response handling for shortcode and external forms.

= 1.0.0 =
* Fixed frontend form submission, local submission storage, and GHL contact sync.

= 0.1.10 =
* Fixed a shortcode renderer fatal error on WordPress installs where the required() helper is unavailable.

= 0.1.9 =
* Made shortcode rendering safer inside popups by preventing placeholder forms from submitting before frontend AJAX handling is implemented.

= 0.1.8 =
* Added frontend shortcode rendering for [ghl_form] with basic responsive form styles.

= 0.1.7 =
* Added basic form management with create/edit forms, default form configurations, Forms list table, shortcode display, duplicate, delete, and copy actions.

= 0.1.6 =
* Refined GHL connection test behavior, masked token display, and connection status card styling.

= 0.1.5 =
* Added GHL Test Connection action, connection status result card, and setup instructions for Location ID and Access Token.

= 0.1.4 =
* Improved Access Token settings with a masked token display, replace field, and Remove button.

= 0.1.3 =
* Removed update repository controls from Settings while keeping GitHub update checks configured in code.

= 0.1.2 =
* Set the default GitHub update repository so plugin-update-checker can boot without manual repository configuration.

= 0.1.1 =
* Added editable Settings page with Location ID, encrypted Access Token storage, update repository options, and data/log toggles.

= 0.1.0 =
* Initial Phase 1 architecture, activation schema, admin menu, and update-checker bootstrap.
