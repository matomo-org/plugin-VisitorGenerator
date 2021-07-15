# Matomo VisitorGenerator Plugin

[![Build Status](https://travis-ci.com/matomo-org/plugin-VisitorGenerator.svg?branch=4.x-dev)](https://travis-ci.com/matomo-org/plugin-VisitorGenerator)

## Description

Plugin to create fake visits, websites, users and goals. This can be used by Matomo users or developers as an easy way to generate fake data to populate Matomo reports.

You can overwrite the log files that are used to generate fake visits in [plugins/VisitorGenerator/data](https://github.com/matomo-org/plugin-VisitorGenerator/blob/master/data) or add new logs to the `data` directory. All files ending with `*.log` will be replayed.

Plugin developers can provide their own log files by placing '*.log' files within a 'data' directory of their plugin. This way plugin developers make sure there will be always useful test data.

### Usage 

#### UI
The plugin adds a new item to the Matomo admin UI visible only for users having Super User access under the section "Development". There you can select a site and for how many days in the past you want to generate new visits.

Note: you need to first enable the Development mode in Matomo. In the root directory of your Matomo install, run the following command to enable development mode: `./console development:enable`


#### CLI
It also adds the following commands to the [Matomo CLI tool](http://developer.matomo.org/guides/piwik-on-the-command-line):

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
* `./console visitorgenerator:generate-visits --idsite 5 --custom-matomo-url=http://example.com/` Uses 'http://example.com/' as Matomo-URL and generates many visits for site with id 5 for today

#### Other notes

VisitorGenerator makes a lot of requests to the Matomo tracking API to send the visits, so if your server blocks requests based on rules (e.g. with mod_security), you might want to create an exception rule for VisitorGenerator.

## Using it in Matomo for WordPress

It only works in Matomo for WordPress if the plugin is installed through git as it's only intended for development.

In Matomo for WordPress you can find the UI in the Matomo Admin under "System".

You can also use the command line to generate the data:

```
cd wp-content/plugins/matomo/app
./console  visitorgenerator:generate-visits --idsite=1
```

### Legalnotice

This plugin is released under the GPLv3+ license.

This plugin uses the [Faker](libs/Faker/readme.md) library which is released under the [MIT license](libs/Faker/LICENSE).
