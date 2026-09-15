=== Selda ===
Contributors: selda
Tags: crm, leads, forms, sales, contact form
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Others sell you a tool. Selda builds your sales machine. Site enquiries land in Selda, where the follow-up is drafted for you.

== Description ==

Every enquiry, quote request and guide download from your site lands in Selda with a timeline and an owner, instead of an inbox where it is easy to miss. Selda reads what the person actually asked and drafts the reply from it.

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
3. Create an API key in Selda under Settings, Connections, MCP server and paste it in. API access is on every plan, the free one included.
4. Pick your project and send a test lead.

== Frequently Asked Questions ==

= Does this send email to my customers? =

No. It records the enquiry in Selda. Replies are drafted there and a person sends them.

= Do I have to replace my contact form? =

No. If you use Contact Form 7, WPForms, Gravity Forms or Elementor Pro, submissions are captured as they happen and your form keeps working exactly as before.

= What is sent to Selda? =

The form fields, the page address and your site domain. Nothing else. No cookies, no visitor tracking.

= Do I need a paid Selda plan? =

No, not to connect. API access is on every Selda plan, the free one included, and a workspace in test mode issues a test key that this plugin works with in full. Taking enquiries into a live workspace is the Inbound intake add-on; without it a live key is refused and the delivery log tells you so.

= Where do I read more? =

Documentation is at https://docs.selda.ai . The API this plugin uses is at https://docs.selda.ai/connect-your-app , and https://docs.selda.ai/how-selda-contacts-people covers what Selda does once a lead arrives.

== Changelog ==

= 0.2.1 =
* Changed: the headline. Selda's own line became "Others sell you a tool. Selda builds your sales machine." on 13.9.2026, and this plugin was still carrying the retired one in four places, including the description WordPress shows in the directory. Nothing about what the plugin does changed.

= 0.2.0 =
* Changed: the plugin says what Selda actually does. Others give you a list; Selda gets you the conversation, and this plugin is the door into it from your site.
* Changed: the API key is created under Settings, Connections, MCP server. The old path no longer exists in the app.
* Added: API access is on every plan, the free one included. Taking enquiries into a live workspace needs the Inbound intake add-on.
* Added: links to the documentation.
* Added: notifications. Selda calls the site when a reply is drafted, when someone answers and when a meeting is booked, and the site passes it on to Slack. Incoming calls are checked against a signature.
* Added: the settings screen says whether the saved key is a sandbox key or a production one, so a test key is not left in place by accident.
* Added: enquiries ask Selda to draft the reply as they arrive, rather than waiting in a list.

= 0.1.1 =
* Fixed: submissions failed on sites whose form labels contain non-ASCII characters, such as Finnish or German. Field names are now transliterated before sending.

= 0.1.0 =
* First release.
