=== Affiliate Sales in Google Analytics and other tools ===
Contributors: wecantrack
Tags: affiliate, publisher, analytics, conversion tracking, sale attribution, dashboard, subid, google analytics, link, google ads, facebook, data studio, we can track, wecantrack, tracking tool
Requires at least: 4.6
Tested up to: 6.4
Requires PHP: 7.3
Stable tag: 1.4.9
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.en.html

Integrate all your affiliate sales in Google Analytics, Google Ads, Facebook, Data Studio and more!

== Description ==
We Can Track is an affiliate data tracking, processing and integration software that attributes affiliate sales data to publishers’ traffic data.
With We Can Track, affiliate publishers can finally integrate their sales data in the marketing tools they use.

Registration is free and you will be able to make use of a 30 days trial period once you connected network accounts.

By installing and enabling the We Can Track plugin, your affiliate links will automatically contain unique SubIDs that will be used to trace a sale back to the click it originated from.

Furthermore, the We Can Track plugin is compliant with most redirection (cloaking) plugins, making automatic SubID placements possible.

== Installation ==

1. In order to make use of the We Can Track WordPress plugin you first need to sign up at https://wecantrack.com
2. Connect your affiliate network account(s) and website(s), as described here: https://wecantrack.com/get-started/
3. Upload the directory wecantrack in /wp-content/plugins/ (or install the plugin over the plugin manager of WordPress)
4. Activate the plugin over the plugin manager of WordPress
5. The plugin creates its own submenu 'wecantrack'. In the menu item 'Settings' insert the API Key and click on `verify` ( Go here to get your API key: https://app.wecantrack.com/user/integrations/wecantrack/api)
6. Before enabling the plugin, perform testing as explained in step 3 in this guide: https://wecantrack.com/wordpress/
7. If testing was unsuccessful please contact support@wecantrack.com.
8. If successful, go back to the `We Can Track > Settings`. On the field `Plugin status` select `enable`, empty the `Enable plugin when URL contains` and click on `Save Changes`
9. Test again
10. Done :)

== Frequently Asked Questions ==

= What happens if for some reason the We Can Track service goes down, will my links break? =
We have developed a fallback to the original link for these situations.

= Redirect/cloaking plugin seems to be not compliant with We Can Track? =
This is a rare occurrence, but it could happen if the redirect is being done on the client-side or if the redirect plugin is not using the Wordpress redirect function.
Not to worry, we'd be happy to help you. Just contact us at support@wecantrack.com with information about your redirection plugin and other relevant information and we'll check for possible solutions.

= Are multi-sites supported? =
No, please contact support@wecantrack.com if you want us to support this.

== Screenshots ==
1. Easily install WeCanTrack on your WordPress site by making use of the WeCanTrack WordPress plugin.
2. Always be up to date about your affiliate performance. All your affiliate network and website data in one dashboard. We collect your sale and traffic data and match it so you can see the performance of your websites, landing pages, traffic sources, advertisers, network accounts and more.

== Translations ==

* English - default, always included

== Upgrade Notice ==

== Changelog ==

= 1.4.9 - 29th May 2024 =
* Do not run WCT on a cron job

= 1.4.8 - 20th March 2024 =
* Redirect Through Parameter fix

= 1.4.7 - 28th December 2023 =
* New setting added to disable redirect through parameter

= 1.4.6 - 16th October 2023 =
* Detect Elementor's & Divi's page builder mode

= 1.4.5 - 17th August 2023 =
* Do not log relative URLs as faulty
* Wordpress 6.3 support

= 1.4.4 - 10th January 2023 =
* Fixed visual bug for disable wct.js script option

= 1.4.3 - 21st December 2022 =
* New option to disable wct.js script

= 1.4.2 - 10th October 2022 =
* Made thrive page builder detection pattern more greedy

= 1.4.1 - 31st August 2022 =
* Improved affiliate url pattern matching

= 1.4.0 - 16th March 2022 =
 * Working on supporting WP multi-site. Better error messages.

= 1.3.0 - 20th December 2021 =
 * Do not enqueue WCT JS Script in Thrive Architect

= 1.2.12 - 27th October 2021 =
 * Overridable ssl verify option

= 1.2.11 - 14th July 2021 =
 * Fix decoded deeplinks

= 1.2.10 - 29th June 2021 =
 * New Settings section.
 * Setting section: new field to turn off referrer cookies
 * Extra failsafe: Double checking if redirect is encoded, if so, we'll decode it before redirecting

= 1.2.9 - 21st June 2021 =
 * Improved error logs

= 1.2.8 - 9th February 2021 =
 * Update JS snippet when Plugin Updates. No need to click on Save on plugin update anymore.

= 1.2.7 - 4th February 2021 =
 * Validation form bugfix for users where site_url() contains URIs

= 1.2.6 - 4th February 2021 =
 * Preload will work with proxy. Moving away from cdn.wecantrack.com.
 * Fix where sometimes the snippet gets cleared when request fails after clicking on save
 * Moving away from app.wecantrack.com redirects, uncloaked URLs will default to internal /go domain instead. This redirect domain can be changed at app.wecantrack.com

= 1.2.5 - 31st December 2020 =
 * Admin JS Fix for WP 5.6+
 * New bot pattern added:bing

= 1.2.4 - 14th December 2020 =
 * Use home_url instead of site_url

= 1.2.3 - 14th December 2020 =
 * Improved cache control

= 1.2.2 - 8th December 2020 =
 * Improved affiliate link checker
 * Extra explanatory text added for Cache and ThirstyAffiliates

= 1.2.1 - 29th September 2020 =
 * Status code fix for Redirect Page and redirect improvements
 * Better redirect cache controls
 * HTTP_REFERER fallback

= 1.2.0 - 3rd September 2020 =
 * WeCanTrack navigation position set to 99 (underneath separator)
 * New module added (Redirect Page)
 * Better debugging capabilities added for the plugin
 * External domain tracking support for custom domains
 * Support to install lighter JS script (wct_session.js)
 * Tracking redirect_url in Clickout API

= 1.1.2 - 23rd July 2020 =
 * Support facebook and youtube clickout referrers

= 1.1.1 - 15th July 2020 =
 * Integrated redirect_through WCT option into the plugin
 * Disable Ezoic for WCT script
 * Load WCT script faster

= 1.1.0 - 18th June 2020 =
 * Affiliate link checker regex fix
 * Bot detection

= 1.0.9 - 16th June 2020 =
 * Use rawurldecode

= 1.0.8 - 29th May 2020 =
 * Optimisations

= 1.0.7 - 26th May 2020 =
 * Fixed admin HTML issue

= 1.0.6 - 23rd May 2020 =
 * Simplify UI flow. Removed custom JS and Resync capabilities. Plugin will automatically handle that information in the background.

= 1.0.5 - 16th May 2020 =
 * Compliant with any redirection plugins

= 1.0.4 - 7th May 2020 =
 * Session based Plugin Enabler Fix

= 1.0.3 - 30th April 2020 =
 * Fix a bug with verifying API key

= 1.0.2 - 29th April 2020 =
 * Compliant with "Affiliate Plug.in"

= 1.0.1 - 29th April 2020 =
 * Support redirect plugins: Redirection, Pretty Links
 * Admin Settings UI added
 * Implement i18n
 * Introduce cache invalidation for snippet.js
 * Only modify supported affiliate URLs

== Credits ==
