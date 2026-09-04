=== GHL Contact Sync ===
Contributors: seamkt
Tags: forms, gohighlevel, crm, contacts, leads
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create reusable frontend forms, store submissions locally, and sync contacts to GoHighLevel.

== Description ==

GHL Contact Sync lets WordPress administrators manage reusable forms and prepare contacts for GoHighLevel synchronization.

== Changelog ==

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
