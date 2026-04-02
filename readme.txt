=== SourceTag ===
Contributors: sourcetag
Tags: attribution, utm, tracking, lead source, marketing
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Captures UTM parameters, click IDs, referrer data, and landing pages in your contact form submissions. First-touch and last-touch attribution.

== Description ==

SourceTag populates hidden fields in your contact forms with marketing attribution data. When a lead submits a form, you see which channel, campaign, and keyword brought them in.

**What gets captured:**

* Channel (Paid Search, Organic Social, Email, etc.)
* UTM parameters (source, medium, campaign, term, content)
* Click IDs (gclid, fbclid, msclkid, gbraid, wbraid)
* Landing page and referrer
* Visit count and days to conversion
* Device type

**Form builders:**

Works with Contact Form 7, Gravity Forms, WPForms, Elementor Forms, Formidable Forms, HubSpot Forms, and any HTML form that supports hidden fields.

**Why use the plugin instead of just adding the script tag?**

The plugin sets cookies via PHP (HTTP headers) instead of JavaScript. Safari, Brave, and other privacy-focused browsers cap JavaScript cookies at 7 days. Server-set cookies last up to 400 days.

If you don't use WordPress, you can still use SourceTag by adding the script tag directly to your site - you just won't get the extended cookie persistence.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/sourcetag/` or install from the .zip file.
2. Activate the plugin.
3. Go to Settings > SourceTag and paste your script URL from the [SourceTag dashboard](https://app.sourcetag.io).

== Frequently Asked Questions ==

= Where do I get my script URL? =

Sign up at [sourcetag.io](https://sourcetag.io), add your site, and copy the script URL from the Install Script section on your site's page.

= Does this create hidden fields in my forms? =

No. You add hidden fields to your forms yourself. SourceTag detects them and fills in the values when a visitor submits. The docs cover how to do this for each form builder.

= What about Safari and Brave? =

JavaScript cookies in these browsers expire after 7 days. The plugin's server-side cookie option (on by default) sets the cookie via PHP instead, which these browsers allow for up to 400 days.

== Changelog ==

= 1.0.1 =
* Fix: server-side cookie setting can now be properly unchecked

= 1.0.0 =
* First release
