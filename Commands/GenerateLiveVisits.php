<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\VisitorGenerator\Commands;

use Piwik\Date;
use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\VisitorGenerator\Generator\LiveVisitsFromLog;
use Piwik\Site;

class GenerateLiveVisits extends ConsoleCommand
{
    /**
     * @var int|null
     */
    private $timeout;

    protected function configure()
    {
        $this->setName('visitorgenerator:generate-live-visits');
        $this->setDescription('Continuously generates visits from a single log file to make it seem like there is real time traffic.');
        $this->addRequiredValueOption('idsite', null, '(required) The ID of the site to track to.');
        $this->addRequiredValueOption('stop-after', null, 'If supplied, the command will exit after this many seconds.');
        $this->addRequiredValueOption('log-file', null,
            '(required) The log file to track visits from. This file MUST have visits in order of time.');
        $this->addRequiredValueOption('day-of-month', null,
            'By default this command starts with visits for the current day of the month. Use this option to force an override. '
            . 'Specify 0 to ignore day of the month and use every log.',
            Date::now()->toString('j'));
        $this->addRequiredValueOption('time-of-day', null,
            'The time of day to start replaying logs for. Defaults to now, specify a value here to override.',
            time() % LiveVisitsFromLog::SECONDS_IN_DAY);
        $this->addRequiredValueOption('custom-matomo-url', null, 'Custom Matomo URL to track to.');
        $this->addRequiredValueOption('timeout', null, "Sets how long, in seconds, the timeout should be for the request.", 10);
        $this->addRequiredValueOption('token-auth', null, 'Use custom token auth instead of system generated one. If running this '
            . 'command continuously or a cron, this should be used, since the generated token auth will expire after 24 hours.');
    }

    /**
     * @return int
     */
    protected function doExecute(): int
    {
        $input = $this->getInput();
        $output = $this->getOutput();
        $piwikUrl = $input->getOption('custom-matomo-url');
        $idSite = $this->getSite();
        $stopAfter = $this->getPostiveIntegerOption('stop-after');
        $logFile = $this->getLogFile();
        $dayOfMonth = $this->getPostiveIntegerOption('day-of-month');
        $timeOfDay = $this->getPostiveIntegerOption('time-of-day');
        $this->timeout = $this->getPostiveIntegerOption('timeout');
        $tokenAuth = $input->getOption('token-auth');

        $timeOfDayDelta = $stopAfter ?: LiveVisitsFromLog::SECONDS_IN_DAY;

        $generateLiveVisits = new LiveVisitsFromLog($logFile, $idSite, $timeOfDay, $timeOfDayDelta, $dayOfMonth, $piwikUrl, $this->timeout);
        if (!empty($tokenAuth)) {
            $generateLiveVisits->setTokenAuth($tokenAuth);
        }

        $output->writeln("Generating logs...");

        $startTime = time();
        while (true) {
            [$count, $nextWaitTime] = $generateLiveVisits->tick();
            if ($count === null) {
                $output->writeln("Found no logs to track for day of month / time of day, exiting.");
                return self::SUCCESS;
            }

            $output->writeln("  tracked $count actions.");

            if ($nextWaitTime === null) {
                $output->writeln("Out of logs, exiting.");
                return self::SUCCESS;
            }

            // nextWaitTime can be large if the next visit happens in an hour. no sense in
            // waiting if it'll be after stopAfter
            if ($stopAfter > 0
                && (time() + $nextWaitTime) - $startTime > $stopAfter
            ) {
                $output->writeln("$stopAfter seconds reached, exiting.");
                return self::SUCCESS;
            }

            $output->writeln("  sleeping {$nextWaitTime}s.");
            sleep($nextWaitTime);

            if ($stopAfter > 0
                && time() - $startTime > $stopAfter
            ) {
                $output->writeln("$stopAfter seconds reached, exiting.");
                return self::SUCCESS;
            }
        }

        return self::SUCCESS; // should never occur
    }

    private function getPostiveIntegerOption($optionName)
    {
        $value = $this->getInput()->getOption($optionName);
        if (!empty($value)
            && (!is_numeric($value)
                || $value < 0)
        ) {
            throw new \Exception("Invalid value for --$optionName option, if supplied, must be positive integer.");
        }
        return (int) $value;
    }

    private function getLogFile()
    {
        $path = $this->getInput()->getOption('log-file');
        if (empty($path)) {
            throw new \Exception("The --log-file option is required.");
        }

        if (!file_exists($path)) {
            throw new \Exception("The '$path' file does not exist.");
        }
        return $path;
    }

    private function getSite()
    {
        $idSite = $this->getInput()->getoption('idsite');
        new Site($idSite); // check it's valid
        return $idSite;
    }
}
