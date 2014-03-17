# Piwik VisitorGenerator Plugin

## Description

Plugin to create fake visits, websites, users and goals. This can be used by Piwik users or developers as an easy way to generate fake data to populate Piwik reports.

You can overwrite the log files that are used to generate fake visits in [plugins/VisitorGenerator/data](https://github.com/piwik/plugin-VisitorGenerator/blob/master/data) or add new logs to the `data` directory. All files ending with `*.log` will be replayed.

The plugin adds a new item to the Piwik admin UI visible only for users having Super User access.

It also adds the following commands to the [Piwik CLI tool](http://developer.piwik.org/guides/piwik-on-the-command-line):

* `visitorgenerator:generate-goals`
* `visitorgenerator:generate-users`
* `visitorgenerator:generate-visits`
* `visitorgenerator:generate-websites`

### Example Usage
* `./console visitorgenerator:generate-goals --idsite 5`   // generates some predefined goals for site with id 5
* `./console visitorgenerator:generate-users --limit 100`  // generates 100 users
* `./console visitorgenerator:generate-websites --limit 100`  // generates 100 websites
* `./console visitorgenerator:generate-visits --idsite 5`  // generates many visits for site with id 5 for today
* `./console visitorgenerator:generate-visits --idsite 5 --days 2`  // generates many visits for site with id 5 for today and yesterday

## Changelog

- 1.0 Initial release
- 1.1 New features:
   * Added CLI commands
   * Added possibility to generate websites, users and goals
   * Replay all log files within the data directory

## Support

Please direct any feedback to [hello@piwik.org](mailto:hello@piwik.org)

### Legalnotice

This plugin is released under the GPLv3+ license.

This plugin uses the [Faker](libs/Faker/readme.md) library which is released under the [MIT license](libs/Faker/LICENSE).
