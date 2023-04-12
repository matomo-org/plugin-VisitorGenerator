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
        $this->addRequiredValueOption('idsite', null, 'Defines the site the visits should be generated for');
        $this->addRequiredValueOption('days', null, 'Defines for how many days in the past visits should be generated', 1);
        $this->addRequiredValueOption('start-date', null, 'Date to start generating on.');
        $this->addNoValueOption('no-fake', null, 'If set, no fake visits will be generated', null);
        $this->addNoValueOption('no-logs', null, 'If set, no visits from logs will be generated', null);
        $this->addRequiredValueOption('limit-fake-visits', null, 'Limits the number of fake visits', null);
        $this->addRequiredValueOption('custom-matomo-url', null, "Defines an alternate Matomo URL, e.g. if Matomo is installed behind a Load-Balancer.");
        $this->addRequiredValueOption('timeout', null, "Sets how long, in seconds, the timeout should be for the request.", 10);
        $this->addNoValueOption('non-profilable', null, "If supplied, tracks data without visitor IDs so it will be considered 'not profilable'.");
    }

    /**
     * @return int
     */
    protected function doExecute(): int
    {
        $input = $this->getInput();
        $output = $this->getOutput();
        $this->timeout =  $input->getOption('timeout');
        $timer = new Timer();
        $days = $this->checkDays();
        $customMatomoUrl = $this->checkCustomMatomoUrl();
        $idSite = $this->getIdSite();

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
                $limit = $this->getLimitFakeVisits();
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

        $this->writeSuccessMessage(array(
            'idsite = ' . $idSite,
            $nbActionsTotal . ' Visits generated',
            round($nbActionsTotal / $timer->getTime(), 0) . ' requests per second'
        ));

        return self::SUCCESS;
    }

    private function getLimitFakeVisits()
    {
        $input = $this->getInput();

        if ($input->getOption('limit-fake-visits')) {

            return $input->getOption('limit-fake-visits');
        }

        return rand(400, 1000);
    }

    private function checkDays()
    {
        $days = (int)$this->getInput()->getOption('days');

        if ($days < 1) {
            throw new \InvalidArgumentException('Days to compute must be greater or equal to 1.');
        }

        return $days;
    }

    private function checkCustomMatomoUrl()
    {
        if (!$customMatomoUrl = $this->getInput()->getOption('custom-matomo-url')) {
            return null;
        }

        if (!UrlHelper::isLookLikeUrl($customMatomoUrl)) {
            throw new \Exception("The Custom Matomo Tracker Url you entered doesn't seem to be valid.");
        }

        return $customMatomoUrl;
    }

    private function getIdSite()
    {
        $idSite = $this->getInput()->getOption('idsite');

        if ($idSite === null) {
            $idSite = $this->ask('ID of the site in which to generate the visits: ');
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