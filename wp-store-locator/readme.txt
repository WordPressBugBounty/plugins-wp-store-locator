=== WP Store Locator ===
Plugin URI: https://wpstorelocator.co
Contributors: tijmensmit
Tags: google maps, store locator, business locations, geocoding, stores, geo, zipcode locator, dealer locater, geocode, gmaps, google map, google map plugin, location finder, map tools, shop locator, wp google map
Requires at least: 3.7
Tested up to: 6.7.2
Stable tag: 2.2.251
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html

An easy to use location management system that enables users to search for nearby physical stores.

== Description ==

WP Store Locator is a powerful and easy to use location management system. 
You can customize the appearance of the map, and provide custom labels for entry fields. 
Users can filter the results by radius, and see driving directions to the nearby stores in 
the language that is set in the admin panel. 

= Features include: =

* Manage an unlimited numbers of stores.
* Provide extra details for stores like the phone, fax, email, url, description and opening hours. There are filters available that allow you add [custom](http://wpstorelocator.co/document/add-custom-meta-data-to-store-locations/) meta data.
* Support for custom [map styles](https://snazzymaps.com/).
* Choose from nine retina ready marker icons.
* Show the driving distances in either km or miles.
* Shortcodes that enable you to add individual opening hours, addresses or just a map with a single marker to any page.
* Compatible with multilingual plugins like [WPML](https://wpml.org/plugin/wp-store-locator/) and qTranslate X.
* You can drag the marker in the editor to the exact location on the map.
* Show the search results either underneath the map, or next to it.
* Show Google Maps in different languages, this also influences the language for the driving directions.
* Show the driving directions to the stores.
* Customize the max results and search radius values that users can select.
* Users can filter the returned results by radius, max results or category.
* Supports [marker clusters](https://developers.google.com/maps/articles/toomanymarkers?hl=en#markerclusterer).
* Customize map settings like the terrain type, location of the map controls and the default zoom level.
* Use the Geolocation API to find the current location of the user and show nearby stores.
* Developer friendly code. It uses custom post types and includes almost 50 different [filters](https://wpstorelocator.co/documentation/filters/) that help you change the look and feel of the store locator.
* GDPR - Load Google Maps only after the user agrees to it ( requires the [Borlabs Cookie](https://borlabs.io/borlabs-cookie/) plugin ).

> <strong>Documentation</strong><br>
> Please take a look at the store locator [documentation](https://wpstorelocator.co/documentation/) before making a support request.

* [Getting Started](https://wpstorelocator.co/documentation/getting-started/)
* [Troubleshooting](https://wpstorelocator.co/documentation/troubleshooting/)
* [Customisations](https://wpstorelocator.co/documentation/customisations/)
* [Filters](https://wpstorelocator.co/documentation/filters/)

= Premium Add-ons =
 
**CSV Manager**

The [CSV Manager](https://wpstorelocator.co/add-ons/csv-manager/) allows you to bulk import, export and update your locations using a CSV file.

**Search Widget**

The [Search Widget](https://wpstorelocator.co/add-ons/search-widget/) enables users to search from any of the widgetized areas in your theme for nearby store locations, and show the results on the store locator page.

**Statistics**

The [Statistics](https://wpstorelocator.co/add-ons/statistics/) add-on enables you to keep track of the locations users are searching for and see where there is demand for a new store.

**Extenders**

The [Extenders](https://www.de-baat.nl/wp-store-locator/wp-store-locator-extenders/) add-on adds the features needed by power users such as managing location based events, social media information and locations managed by other logged in users.

== Installation ==

1. Upload the `wp-store-locator` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Create a [Google API Key](https://wpstorelocator.co/document/create-google-api-keys/) and set them on the [settings](https://wpstorelocator.co/document/configure-wp-store-locator/#google-maps-api) page.
1. Set the start point on the [settings](https://wpstorelocator.co/document/configure-wp-store-locator/#map).
1. Add your stores under 'Store Locator' -> Add Store
1. Add the map to a page with this shortcode: [wpsl]

== Frequently Asked Questions ==

= How do I add the store locator to a page? =

Add this [shortcode](https://wpstorelocator.co/document/shortcodes/) [wpsl] to the page where you want to display the store locator.

= Oops! Something went wrong =

You can fix this by setting the [browser](https://wpstorelocator.co/document/configure-wp-store-locator/#google-maps-api) key on the settings page.

= There are weird characters in the search results, how do I remove them? =

This is most likely caused by a plugin like W3 Total Cache that tried to minify the HTML output on the store locator page. You can fix this by excluding the store locator from being minified on the settings page of the caching plugin you're using. In W3 Total Cache this is done by going to Minify -> Advanced -> Never minify the following pages, and fill in the page you don't want to have minified. So if your store locator is used on mydomain.com/store-locator, then fill in 'store-locator'.

= Can I use different markers for category or individual store locations? =

How to use custom markers is described [here](https://wpstorelocator.co/document/use-custom-markers/), you can also only use [different markers](https://wpstorelocator.co/document/use-custom-markers/) for a few locations, or just for the [categories](https://wpstorelocator.co/document/set-unique-category-markers/).

= The map doesn't display properly. It's either broken in half or doesn't load at all. =

Make sure you have defined a start point for the map under settings -> Map Settings.

= The map doesn't work anymore after installing the latest update =

If you use a caching plugin, or a service like Cloudflare, then make sure to flush the cache.

= I can't dismiss the pop up asking me to join the mailing list, how do I fix this? =

There is probably a JS error in the WP Admin area that prevents the pop up from being dismissed. Try for a second to switch back to a default WP theme, disable all other plugins, and then try to dismiss the newsletter pop up again.

= Why does it show the location I searched for in the wrong country? =

Some location names exist in more then one country, and Google will guess which one you mean. This can be fixed by setting the correct 'Map Region' on the settings page -> API Settings.

= The store locator doesn't load, it only shows the number 1? =

This is most likely caused by your theme using ajax navigation ( the loading of content without reloading the page ), or a conflict with another plugin. Try to disable the ajax navigation in the theme settings, or deactivate the plugin that enables it to see if that solves the problem.

If you don't use ajax navigation, but do see the number 1 it's probably a conflict with another plugin. Try to disable the plugins one by one to see if one of them is causing a conflict.

If you find a plugin or theme that causes a conflict, please report it on the [support page](http://wordpress.org/support/plugin/wp-store-locator).

> You can find the full documentation [here](https://wpstorelocator.co/documentation/).

== Screenshots ==

1. Front-end of the plugin
2. The driving directions from the user location to the selected store
3. The 'Store Details' section
4. The plugin settings

== Changelog ==

= 2.2.251
* Fixed: Security issue ( Stored XSS vulnerability ) in the translation strings. Thanks to [cleantalk](https://cleantalk.org/) for reporting this.

= 2.2.250
* New: Added support for the new [Places API](https://developers.google.com/maps/documentation/javascript/place). You can find instructions on migrating to the new API [here](https://wpstorelocator.co/migrate-to-the-new-places-api/).

= 2.2.241
* Fixed: Not all markers showing up when multiple [wpsl_map] shortcodes are used on the same page.
* Fixed: PHP 8.2 compatiblity issues.
* Fixed: The custom map style not being applied to the preview map on the WPSL settings page.
* Changed: Removed broken links to map style examples from the settings page.

= 2.2.240
* New: To reduce the chance of seeing the 'wpslCallback is not a function' error in the browser console ( and the map breaking ), the required JS files are now automatically excluded from the SiteGround Optimizer, Autoptimize, LiteSpeed Cache and WP Rocket plugins.
* New: Optional deactivation survey on live sites to gather feedback to improve the plugin ( nothing is shown on dev domains / localhosts ).
* Fixed: Cannot read property '__e3_' of undefined error when the marker cluster and the custom info window script is used together.
* Fixed: Uncaught TypeError: callback is not a function when the start marker is dragged to a new location after a search is finished.
* Fixed: The start marker duplicating itself when it's dragged to a new location when the 'Attempt to auto-locate the user' option is enabled.
* Fixed: Uncaught ReferenceError: InfoBox is not defined when the Borlabs Cookie plugin is active in combination with the custom infobox.js script.
* Fixed: Different JS event handlers not firing correctly when the Borlabs Cookie plugin is active.
* Changed: Make sure HTTPS is always used for request to the Google Maps API in the admin area.
* Changed: Included the latest version of the EDD_SL_Plugin_Updater class ( 1.9.1 ).

= 2.2.237
* Fixed: The "Loading the Google Maps JavaScript API without a callback is not supported" console warning.
* Note: If you have the search widget add-on installed and are using the autocomplete function, then please make sure to update to the latest version ( 1.2.1 ).

= 2.2.236
* Fixed: The search function can break if the created opening hours doesn't contain every day of the week.

= 2.2.235
* Fixed: [Borlabs Cookie](https://borlabs.io/borlabs-cookie/) compatiblity problem with the Google Maps placeholder.

= 2.2.234
* Fixed: PHP 8 Compatiblity
* Fixed: Polylang Compatiblity

= 2.2.233
* Fixed: A problem with the [Borlabs Cookie](https://borlabs.io/borlabs-cookie/) plugin where Google Maps wasn't blocked correctly.
* Changed: Set the loaded Google Maps JavaScript [version](https://developers.google.com/maps/documentation/javascript/versions) to quarterly. So every quarter it will automatically load the latest version.

= 2.2.232
* Fixed: The opening hours triggering an error on some server configurations.
* Fixed: Restored the [wpsl_skip_cpt_template](https://wpstorelocator.co/document/wpsl_skip_cpt_template/) filter which was accidently removed in a previous update.
* Changed: Set the loaded Google Maps JavaScript [version](https://developers.google.com/maps/documentation/javascript/versions) to 3.39 instead of 3.36.

= 2.2.231
* Fixed: Bug that in some cases broken the Borlabs Cookie plugin from working correctly together with the store locator plugin.

= 2.2.23
* Fixed: The export data function on the single location pages in the admin area not working.
* Fixed: The map and location data not showing up in the TwentyNineteen theme on individual location pages.
* Changed: Updated the included EDD_SL_Plugin_Updater class that handles updates / license key checks for add-ons.

= 2.2.22
* Added: Support for the [Borlabs Cookie](https://borlabs.io/borlabs-cookie/) plugin. This allows you to [block the loading](https://wpstorelocator.co/document/the-general-data-protection-regulation/#borlabs) of Google Maps until the user agrees to it.
* Added: A 'Validate API Keys' tool that will provided feedback for any issues with the used API keys.
* Added: A tool that will show you the raw Google Geocode API response for any address you provide based on the current configuration together with a preview map.
* Changed: Made sure that links included in error messages from the Google Geocode API are always clickable.
* Changed: If no zip code is included in the returned location data after the users location is automatically determined, then it will now show the city / town name in the search field instead of leaving it empty.

= 2.2.21
* Changed: Set the loaded Google Maps JavaScript [version](https://developers.google.com/maps/documentation/javascript/versions) to 3.36 instead of 3.33.

= 2.2.20
* New: Added an option to the permalinks section to optionally remove the front base ( for example /blog/ ) from the store locator permalinks.
* New: Added an option to the search section to force the Google Geocode API to assume the search input is a zip code. So searching for town / city names won't work if this option is enabled.
* New: Included a [wpsl_autocomplete_options](https://wpstorelocator.co/document/wpsl_autocomplete_options/) filter to customize the included data in requests made to the places API ( used with autocomplete ).
* Changed: Limited the requested data for the places API ( used with autocomplete ) to only 'geometry.location', which should reduce the cost on high traffic websites.
* Removed: Unused CSS rules from the admin CSS.
* Fixed: Using keyboard input to select a location after the first search with the autocomplete option enabled now returns the correct location.