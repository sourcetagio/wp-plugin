# SourceTag WordPress Plugin

Tracks where your leads come from. Captures UTM parameters, click IDs, referrer data, and landing pages in your form submissions.

## What it does

- First-click and last-click attribution
- 400-day cookie persistence via server-side cookies (gets around Safari's 7-day JS cookie limit)
- Works with Contact Form 7, Gravity Forms, WPForms, Elementor Forms, HubSpot Forms, and any HTML form
- You just need to add hidden fields to your forms — the plugin populates them

## Install

1. Download the latest .zip from [Releases](https://github.com/sourcetagio/wp-plugin/releases)
2. In WordPress: Plugins > Add New > Upload Plugin
3. Upload and activate
4. Go to Settings > SourceTag and paste your Script URL from the [SourceTag dashboard](https://app.sourcetag.io)

Full setup guide: [sourcetag.io/docs/install-wordpress](https://sourcetag.io/docs/install-wordpress)

## How it works

The plugin loads the SourceTag tracking script in your `<head>` and re-sets the attribution cookie via PHP `setcookie()` on each page load. This extends cookie life from 7 days (Safari's JS limit) to 400 days.

There's also a REST endpoint (`/wp-json/sourcetag/v1/set-cookie`) that the script calls on a visitor's first visit to set the cookie before the PHP refresh kicks in.

## Requirements

- WordPress 5.8+
- PHP 7.4+
- A SourceTag account ([sourcetag.io](https://sourcetag.io))
