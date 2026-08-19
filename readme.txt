=== Selda ===
Contributors: selda
Tags: crm, leads, forms, sales, contact form
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn your website into a sales engine. Enquiries go straight into Selda, where the follow-up is drafted for you.

== Description ==

Every enquiry, quote request and guide download from your site lands in Selda with a timeline and an owner, instead of an inbox where it is easy to miss.

Nothing is ever sent to anyone automatically. Drafts wait for a human to press send in the Selda app.

**Why not just email?**

Many hosts disable PHP mail() or block outbound SMTP, so form messages never leave the server while the form still looks like it worked. This plugin uses ordinary HTTPS, which is open everywhere, and logs every delivery so you can tell a quiet week from a broken form.

**Features**

* Connect with an API key, pick a project, send a test lead
* Captures Contact Form 7, WPForms, Gravity Forms and Elementor Pro submissions automatically
* Build forms with the [selda_form] shortcode
* Point a form at a specific campaign, so a guide download and a quote request get different follow-up
* Delivery log with a warning when something stops working

== Installation ==

1. Upload the plugin and activate it.
2. Open Selda in the admin menu.
3. Create an API key in Selda under Settings, Apps, Selda MCP and paste it in.
4. Pick your project and send a test lead.

== Frequently Asked Questions ==

= Does this send email to my customers? =

No. It records the enquiry in Selda. Replies are drafted there and a person sends them.

= Do I have to replace my contact form? =

No. If you use Contact Form 7, WPForms, Gravity Forms or Elementor Pro, submissions are captured as they happen and your form keeps working exactly as before.

= What is sent to Selda? =

The form fields, the page address and your site domain. Nothing else. No cookies, no visitor tracking.

== Changelog ==

= 0.1.1 =
* Fixed: submissions failed on sites whose form labels contain non-ASCII characters, such as Finnish or German. Field names are now transliterated before sending.

= 0.1.0 =
* First release.
