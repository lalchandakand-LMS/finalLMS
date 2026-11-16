# Popup Ads & Announcements Data Schema

This plugin owns the configurable popup campaigns (`visitor_popups`) and
privacy-conscious daily aggregate event counters (`popup_ad_events`).

The signed package migration runs through the LMS Plugin Migration Runner only
after signature verification, private-path installation and installed-file
integrity checks. When upgrading an existing installation that already ran the
earlier `create_visitor_popups_table` migration, new layout, targeting,
schedule, frequency and analytics columns are added non-destructively.

Deactivation and package uninstall do not drop tables or delete campaign data so
administrators keep campaigns and analytics across updates. Rolling back the
migration refuses to drop tables while campaigns or analytics exist; use the
admin JSON Export and confirmed Purge tools before removing retained data.
