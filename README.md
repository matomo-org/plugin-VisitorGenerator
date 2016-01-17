# Piwik VisitorGenerator Plugin

[![Build Status](https://travis-ci.org/piwik/plugin-VisitorGenerator.svg?branch=master)](https://travis-ci.org/piwik/plugin-VisitorGenerator)

## Description

Plugin to create fake visits, websites, users and goals. This can be used by Piwik users or developers as an easy way to generate fake data to populate Piwik reports.

You can overwrite the log files that are used to generate fake visits in [plugins/VisitorGenerator/data](https://github.com/piwik/plugin-VisitorGenerator/blob/master/data) or add new logs to the `data` directory. All files ending with `*.log` will be replayed.

Plugin developers can provide their own log files by placing '*.log' files within a 'data' directory of their plugin. This way plugin developers make sure there will be always useful test data.

### Usage 

#### UI
The plugin adds a new item to the Piwik admin UI visible only for users having Super User access under the section "Development". There you can select a site and for how many days in the past you want to generate new visits.

#### CLI
It also adds the following commands to the [Piwik CLI tool](http://developer.piwik.org/guides/piwik-on-the-command-line):

* Generate visits
* Generate goals
* Generate users
* Generate websites
* Generate annotation
* Shorten log file
* Anonymize log file

##### Examples
* `./console visitorgenerator:generate-annotation --idsite 5` generate one annotation for the current day for site with id 5
* `./console visitorgenerator:generate-goals --idsite 5` generates some predefined goals for site with id 5
* `./console visitorgenerator:generate-users --limit 100`  generates 100 users
* `./console visitorgenerator:generate-websites --limit 100` generates 100 websites
* `./console visitorgenerator:generate-visits --idsite 5`  generates many visits for site with id 5 for today
* `./console visitorgenerator:generate-visits --idsite 5 --days 2` generates many visits for site with id 5 for today and yesterday
* `./console visitorgenerator:anonymize-log /path/to/log` takes an Apache log file, anonymizes it and places it in a data directory so it will be replayed the next time "generate-visits" is executed
* `./console visitorgenerator:shorten-log /path/to/file.log > file.short.log` takes a large Apache log file and keeps only a small number of logs per day
* `./console visitorgenerator:generate-visits --idsite 5 --custom-piwik-url=http://example.com/` Uses 'http://example.com/' as Piwik-URL and generates many visits for site with id 5 for today

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

## Support

Please direct any feedback to [hello@piwik.org](mailto:hello@piwik.org)

### Legalnotice

This plugin is released under the GPLv3+ license.

This plugin uses the [Faker](libs/Faker/readme.md) library which is released under the [MIT license](libs/Faker/LICENSE).
