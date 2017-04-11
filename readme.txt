=== Content Expiration ===
Contributors: srg-1
Tags: expiration, posts, pages
Requires at least: 4.6
Tested up to: 4.7.3
Stable tag: trunk
License: MIT
License URI: https://choosealicense.com/licenses/mit/

Content Expiration is a WordPress plugin to set an expiration date on posts or pages.

== Description ==

*Content Expiration* is a WordPress plugin that allows authors/editors to set an expiration date on posts or pages. The plugin is intended to be simple with easy-to-read code.

An expiration can be set by specifying a number of days (e.g., _expire in 90 days_) or a specific date/time (e.g., _May 10, 2018 at 10:00 PM_). Two weeks prior to the expiration date, the author of the post/page is sent a warning email. The author receives another notification email upon expiration.

When a post/page expires, it is *not* deleted. Instead, it is given a status of _Expired_ and hidden from public view. Visitors to the site will receive the regular WordPress "404 Page Not Found" error.

The expiration can be reset by any editor or administrator of the site. They must log in and edit the expiration settings of the post/page.

*Content Expiration* is actively developed on GitHub. Please submit any issues on GitHub: [https://github.com/srguglielmo/Content-Expiration](https://github.com/srguglielmo/Content-Expiration)

== Installation ==

== Frequently Asked Questions ==

= The timezone seem to be wrong! =

This is a [known issue](https://github.com/srguglielmo/Content-Expiration/issues/2). The plugin is currently hardcoded to the _America/New_York_ timezone. This will be fixed in the next release.

= What's this MIT license? =

*Content Expiration* is released under the [MIT license](https://choosealicense.com/licenses/mit/). The MIT license is short, simple, and very permissive. Basically, you can do whatever you want, provided the original copyright and license notice are included in any/all copies of the software. You may modify, distribute, sell, incorporate into proprietary software, use privately, and use commerically.

There is no warranty and the author or any contributors are not liable if something goes wrong.

See the `LICENSE` file for full details.

== Changelog ==

= 1.0 =
* Initial release.
