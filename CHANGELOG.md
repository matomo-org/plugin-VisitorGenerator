## Changelog

- 1.0 Initial release
- 1.1 New features:
   * Added CLI commands
   * Added possibility to generate websites, users and goals
   * Replay all log files within the data directory
- 1.2 New features:
   * New log file added
   * Added possibility to shorten and anonymize log files
   * Added possibility to let plugins define their own log files
   * Added possibility to generate annotations
   * Replay only log entries having the same day of the month
- 1.2.1 New workaround:
   * When force_ssl is enabled, and visits are generated on `localhost`, force to use HTTP instead of HTTPS
- 1.2.3 Minor UI tweaks to make it consistent with Piwik look & feel
- 3.0.0
   * Compatibility with Piwik 3.0
- 3.0.1
   * Adds tracking of bandwidth
   * Adds tracking of custom dimensions and ecommerce cart updates + orders
- 3.1.0
   * Add new command to log visits live as if they were from real incoming traffic
- 3.1.1
   * Correct URL processing in manipulateRequestUrl after LogHelper regex change
   * Fix log out of order exception that occurs on large logs 
   * Adds search engine referrers without keyword
- 3.1.2
   * New timeout option
- 3.2.0
   * PHP 7.4 compatibility
   * Rename from Piwik to Matomo
   * command line option `custom-piwik-url` was removed, use `custom-matomo-url` instead
- 4.0.0
   * Compatibility with Matomo 4
- 4.0.1
   * Fix generating visits may cause issues in Matomo 4
- 4.0.2
  * Compatibility with Matomo 4
- 4.0.3
  * Adds tracking for media analytics plugin
  * Fix menu item visibility
  * Translation updates
- 4.0.4
  * Add support for Matomo for WordPress
- 4.0.5
  * Fix deprecation warnings for php8.1
- 4.0.6
  * Translation changes
- 4.0.7
  * Ensure console commands return integers
- 5.0.0
  * Compatibility with Matomo 5
- 5.0.1
  * Added generate-visits-db command to directly insert visits into the db
- 5.0.2
  * Updated README url in UI
- 5.0.3
  * Added plugin category for Marketplace
- 5.1.0 - 2024-09-09
  * Replaced Symfony\Process with Piwik\Process
