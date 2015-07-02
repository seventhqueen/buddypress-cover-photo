=== BuddyPress Cover Photo ===
Contributors: seventhqueen
Tags: BuddyPress, avatar, cover, members, groups
Requires at least: 4.1
Tested up to: 4.2
Stable tag: 1.1.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==

Enable your site users to add beautiful profile photo covers to their page.
Admin user also can set covers for other users by visiting their profile.
Group admin can set covers for Groups
From WP admin - Settings - BuddyPress - Settings you can set default images for profile and groups.

NEW: Version 1.1 is out and we added Groups covers and Default cover setting

Check out this demo to see it in action: 
http://seventhqueen.com/themes/kleo/members/kleoadmin/

== Installation ==
= To Install: =

1.  Download the plugin file
2.  Unzip the file into a folder on your hard drive
3.  Upload the `/buddypress-cover-photo/` folder to the `/wp-content/plugins/` folder on your site
4.  Single-site BuddyPress go to Plugins menu and activate there.
5.  For Multisite visit Network Admin -> Plugins and Network Activate it there.

== Frequently Asked Questions ==

= What other configurations do I need =
No. This plugin needs no configuration.

= How do I set up default cover images =
From WP admin - Settings - BuddyPress - Settings you can set default images for profile and groups.


== Changelog ==

= 1.1.4 =
- Change to the group cover tint inner layer to show also when the user isn't logged in. thanks @sharmstr
- Updated translation files

= 1.1.3 =
- Some extra checks when saving just the position so it won't remove the existing image
- Some fixes to allow other themes to filter the backround html tag

= 1.1.2 =
- Fixed header info section of the plugin that was causing some install problems

= 1.1.1 =
- Fixed a BuddyPress Group editing issue from admin area as in: https://wordpress.org/support/topic/compatibility-problem-with-buddypress
- Added html tag filter to hook into the background element if theme developers need to

= 1.1 =
- Added Group cover
- Added settings for default profile and group covers in Settings - BuddyPress - Settings screen

= 1.0.5 =
- Fixed translations

= 1.0.4 =
- Allow admin to set covers for members

= 1.0.3 =
Initial release