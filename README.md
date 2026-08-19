# Selda for WordPress

Turn your website into a sales engine. Every enquiry, quote request and
guide download goes straight into [Selda](https://selda.ai), where the
follow-up is drafted for you.

Nothing is ever sent to anyone automatically. Drafts wait for a human to
press send in the Selda app.

## Why this exists

Most website enquiries arrive as email, and email is the weakest link in
the chain:

- Many hosts disable PHP's `mail()` or block outbound SMTP, so the message
  never leaves the server. The form looks like it worked.
- A message that does arrive lands in an inbox with no history, no owner
  and no reminder.
- When nothing comes in, you cannot tell whether the market went quiet or
  the form broke.

This plugin talks to Selda over ordinary HTTPS, which is open on every
host. The lead lands in a CRM with a timeline, and the delivery log tells
you it arrived.

## Install

1. Download the latest release and upload it under **Plugins → Add New →
   Upload Plugin**, or clone this repository into `wp-content/plugins/`.
2. Activate **Selda**.
3. Open **Selda** in the admin menu.

## Connect

1. In Selda, go to **Settings → Apps → Selda MCP** and create an API key.
2. Paste it into the plugin and press **Connect**.
3. Pick the project. If your key can see only one, it is chosen for you.
4. Press **Send test lead** and watch it arrive.

The key is stored in the WordPress options table and is never written into
a page, a form or the front end.

## Capture forms you already have

If Contact Form 7, WPForms, Gravity Forms or Elementor Pro is installed,
submissions are captured automatically. Your forms keep working exactly as
before: the plugin reads submissions as they happen and never blocks or
alters them.

Field names differ from site to site, so the email, phone, name and
company are recognised from the label and the value. Everything submitted
is stored on the lead regardless, so nothing is lost even when a field is
not recognised.

## Build a form

```
[selda_form]
```

A lead magnet that feeds one campaign and reveals the download:

```
[selda_form
  fields="email"
  button="Get the guide"
  campaign="<campaign id>"
  type="guide_downloaded"
  thanks="Here it is."]
```

| Attribute  | What it does |
|------------|--------------|
| `fields`   | Comma separated: `name`, `email`, `phone`, `company`, `message`. Default `name,email,message`. |
| `required` | Which of those must be filled. Default `email`. |
| `campaign` | Campaign id for this form only. Overrides the site default. |
| `type`     | Event name in Selda, for example `quote_requested` or `guide_downloaded`. |
| `tags`     | Comma separated tags added to the lead. |
| `button`   | Button label. |
| `thanks`   | Shown in place of the form afterwards. |
| `class`    | Extra CSS class on the wrapper. |

Why campaigns matter: a guide download and a quote request are different
conversations. Someone downloading a checklist may be months from booking,
someone asking for a price is ready now. Pointing them at different
campaigns gives each the right tone and follow-up rhythm.

### Styling

The form inherits your theme. Only layout and focus states are set, and
the button is outlined so it stays legible on any background. Restyle
`.selda-form`, `.selda-field` and `.selda-submit` freely.

The form submits in the background so the page does not reload. Without
JavaScript it falls back to a normal POST, because a form that only works
with JavaScript is a form that sometimes does not work.

## Hooks

```php
// Change what is sent, or stop it.
add_filter( 'selda_lead_args', function ( $args, $context ) {
    $args['tags'][] = 'from-' . $context;
    return $args;
}, 10, 2 );
```

## Privacy

The plugin sends the form fields, the page address and the site domain to
Selda. Nothing else. It sets no cookies and does not track visitors.

If you collect contact details, say so in your privacy policy and name
Selda as a processor. Storing enquiries in a CRM is ordinary business use,
but people are entitled to know where their details go.

## Requirements

WordPress 5.8, PHP 7.4, outbound HTTPS. No other plugin required.

## Support

Issues and pull requests are welcome. For questions about Selda itself see
[selda.ai](https://selda.ai).

## Licence

GPL-2.0-or-later.
