=== Content Expiration ===
Contributors: srg-1
Tags: expiration, posts, pages
Requires at least: 4.6
Tested up to: 4.7.3
License: MIT
License URI: https://choosealicense.com/licenses/mit/

Content Expiration is a WordPress plugin to set an expiration date on posts or pages.

== Description ==

**Content Expiration** is a WordPress plugin that allows authors/editors to set an expiration date on posts or pages. The plugin is intended to be simple with easy-to-read code.

An expiration can be set by specifying a number of days (e.g., *expire in 90 days*) or a specific date/time (e.g., *May 10, 2018 at 10:00 PM*). Two weeks prior to the expiration date, the author of the post/page is sent a warning email. The author receives another notification email upon expiration.

When a post/page expires, it is **not** deleted. Instead, it is given a status of *Expired* and hidden from public view. Visitors to the site will receive the regular WordPress *404 Page Not Found* error.

The expiration can be reset by any editor or administrator of the site. They must log in and edit the expiration settings of the post/page.

**Content Expiration** is actively developed on GitHub. Please submit bugs reports and contributions on [the GitHub Project page](https://github.com/srguglielmo/Content-Expiration).

For general support and questions, please use the [WordPress support forum](https://wordpress.org/support/plugin/content-expiration/).

== Installation ==

Install the plugin as you do normally. Once activated, you will have a widget to set an expiration date when creating or editing a post/page. Additionally, there will be a new column, *Expiration*, when viewing all posts/pages in the admin dashboard.

== Frequently Asked Questions ==

= The timezone seem to be wrong! =

This is a [known issue](https://github.com/srguglielmo/Content-Expiration/issues/2). The plugin is currently hardcoded to the `America/New_York` timezone. This will be fixed in the next release.

= Can I set an expiration on custom post types? =

[Not yet](https://github.com/srguglielmo/Content-Expiration/issues/1).

= The page has expired but it's still visible to the public! =

Expirations are processed hourly. Wait a bit, then check again.

= Expired posts aren't listed on the All Pages screens! =

Expired content is not included in the *All* listings. This is a (seven year old) [bug in WordPress](https://core.trac.wordpress.org/ticket/12706). Click the *Expired (x)* link to view the expired content.

= What's this MIT license? =

**Content Expiration** is released under the [MIT license](https://choosealicense.com/licenses/mit/). The MIT license is short, simple, and very permissive. Basically, you can do whatever you want, provided the original copyright and license notice are included in any/all copies of the software. You may modify, distribute, sell, incorporate into proprietary software, use privately, and use commerically.

There is no warranty and the author or any contributors are not liable if something goes wrong.

See the `LICENSE` file for full details.

== Screenshots ==

1. Setting a new expiration on a post or page.
2. Editing an existing expiration date.
3. The *All Pages* listing with the *Expiration* column.
4. The *All Pages* listing with a page that has expired. Remember to click the *Expired* link to view expired content (see the FAQ).
5. The email notifying the author that their post will expire.

== Changelog ==

= 1.0 =
* Initial release.
