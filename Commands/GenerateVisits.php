<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\VisitorGenerator\Commands;

use Piwik\Access;
use Piwik\Date;
use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\VisitorGenerator\Generator\VisitsFake;
use Piwik\Plugins\VisitorGenerator\Generator\VisitsFromLogs;
use Piwik\Site;
use Piwik\Timer;
use Piwik\UrlHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateVisits extends ConsoleCommand
{

    /**
     * @var int|null
     */
    private $timeout;

    protected function configure()
    {
        $this->setName('visitorgenerator:generate-visits');
        $this->setDescription('Generates many visits for a given amount of days in the past. This command is intended for developers.');
        $this->addOption('idsite', null, InputOption::VALUE_REQUIRED, 'Defines the site the visits should be generated for');
        $this->addOption('days', null, InputOption::VALUE_REQUIRED, 'Defines for how many days in the past visits should be generated', 1);
        $this->addOption('start-date', null, InputOption::VALUE_REQUIRED, 'Date to start generating on.');
        $this->addOption('no-fake', null, InputOption::VALUE_NONE, 'If set, no fake visits will be generated', null);
        $this->addOption('no-logs', null, InputOption::VALUE_NONE, 'If set, no visits from logs will be generated', null);
        $this->addOption('limit-fake-visits', null, InputOption::VALUE_REQUIRED, 'Limits the number of fake visits', null);
        $this->addOption('custom-matomo-url', null, InputOption::VALUE_REQUIRED, "Defines an alternate Matomo URL, e.g. if Matomo is installed behind a Load-Balancer.");
        $this->addOption('timeout', null, InputOption::VALUE_REQUIRED, "Sets how long, in seconds, the timeout should be for the request.", 10);
        $this->addOption('non-profilable', null, InputOption::VALUE_NONE, "If supplied, tracks data without visitor IDs so it will be considered 'not profilable'.");
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->timeout =  $input->getOption('timeout');
        $timer = new Timer();
        $days = $this->checkDays($input);
        $customMatomoUrl = $this->checkCustomMatomoUrl($input);
        $idSite = $this->getIdSite($input, $output);

        $trackNonProfilable = $input->getOption('non-profilable');

        $startDate = $input->getOption('start-date');
        if (empty($startDate) || $startDate == 'now') {
            $startTime = time();
        } else {
            $startTime = strtotime($startDate);
        }

        $time = $startTime - ($days - 1) * 86400;

        $nbActionsTotal = 0;
        while ($time <= $startTime) {
            $output->writeln(array(
                sprintf("Generating visits for %s...", Date::factory($time)->toString())
            ));

            if (!$input->getOption('no-fake')) {
                $limit = $this->getLimitFakeVisits($input);
                Access::doAsSuperUser(function () use ($time, $idSite, $limit, &$nbActionsTotal, $customMatomoUrl, $trackNonProfilable) {
                    $fakeVisits = new VisitsFake($customMatomoUrl);
                    $fakeVisits->setTrackNonProfilable($trackNonProfilable);
                    $nbActionsTotal += $fakeVisits->generate($time, $idSite, $limit);
                });
            }

            if (!$input->getOption('no-logs')) {
                Access::doAsSuperUser(function () use ($time, $idSite, &$nbActionsTotal, $customMatomoUrl, $trackNonProfilable) {
                    $fromLogs = new VisitsFromLogs($customMatomoUrl);
                    $fromLogs->setTrackNonProfilable($trackNonProfilable);
                    $nbActionsTotal += $fromLogs->generate($time, $idSite, $this->timeout);
                });
            }

            $time += 86400;
        }

        $this->writeSuccessMessage($output, array(
            'idsite = ' . $idSite,
            $nbActionsTotal . ' Visits generated',
            round($nbActionsTotal / $timer->getTime(), 0) . ' requests per second'
        ));

        return self::SUCCESS;
    }

    private function getLimitFakeVisits(InputInterface $input)
    {
        if ($input->getOption('limit-fake-visits')) {

            return $input->getOption('limit-fake-visits');
        }

        return rand(400, 1000);
    }

    private function checkDays(InputInterface $input)
    {
        $days = (int)$input->getOption('days');

        if ($days < 1) {
            throw new \InvalidArgumentException('Days to compute must be greater or equal to 1.');
        }

        return $days;
    }

    private function checkCustomMatomoUrl(InputInterface $input)
    {
        if (!$customMatomoUrl = $input->getOption('custom-matomo-url')) {
            return null;
        }

        if (!UrlHelper::isLookLikeUrl($customMatomoUrl)) {
            throw new \Exception("The Custom Matomo Tracker Url you entered doesn't seem to be valid.");
        }

        return $customMatomoUrl;
    }

    private function getIdSite(InputInterface $input, OutputInterface $output)
    {
        $idSite = $input->getOption('idsite');

        if ($idSite === null) {
            $idSite = $this->ask($input, $output, 'ID of the site in which to generate the visits: ');
        }

        $idSite = (int)$idSite;

        return Access::doAsSuperUser(function () use ($idSite) {
            if (!Site::getSite($idSite)) {
                throw new \InvalidArgumentException('idsite is not a valid, no such site found');
            }

            return $idSite;
        });
    }
}