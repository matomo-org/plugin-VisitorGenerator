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