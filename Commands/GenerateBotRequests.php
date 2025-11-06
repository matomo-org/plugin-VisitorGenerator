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
use Piwik\Plugins\VisitorGenerator\Generator\BotRequestsFake;
use Piwik\Site;
use Piwik\Timer;
use Piwik\UrlHelper;

class GenerateBotRequests extends ConsoleCommand
{
    protected function configure()
    {
        $this->setName('visitorgenerator:generate-bot-requests');
        $this->setDescription('Generates many bot requests for a given amount of days in the past. This command is intended for developers.');
        $this->addRequiredValueOption('idsite', null, 'Defines the site the requests should be generated for');
        $this->addRequiredValueOption('days', null, 'Defines for how many days in the past requests should be generated', 1);
        $this->addRequiredValueOption('start-date', null, 'Date to start generating on.');
        $this->addRequiredValueOption('limit-fake-requests', null, 'Limits the number of fake requests', null);
        $this->addRequiredValueOption('custom-matomo-url', null, "Defines an alternate Matomo URL, e.g. if Matomo is installed behind a Load-Balancer.");
    }

    /**
     * @return int
     */
    protected function doExecute(): int
    {
        $input  = $this->getInput();
        $output = $this->getOutput();
        $timer  = new Timer();
        $days   = $this->checkDays();
        $customMatomoUrl = $this->checkCustomMatomoUrl();
        $limit  = $this->getLimitFakeRequests();
        $idSite = $this->getIdSite();

        $startDate = $input->getOption('start-date');
        if (empty($startDate) || $startDate === 'now') {
            $startTime = time();
        } else {
            $startTime = strtotime($startDate);
        }

        $time = $startTime - ($days - 1) * 86400;

        $nbActionsTotal = 0;
        while ($time <= $startTime) {
            $output->writeln([
                sprintf("Generating requests for %s...", Date::factory($time)->toString()),
            ]);

            Access::doAsSuperUser(function () use ($time, $idSite, &$nbActionsTotal, $customMatomoUrl, $limit) {
                $fakeRequests = new BotRequestsFake($customMatomoUrl);
                $nbActionsTotal += $fakeRequests->generate($time, $idSite, $limit);
            });

            $time += 86400;
        }

        $this->writeSuccessMessage([
            'idsite = ' . $idSite,
            $nbActionsTotal . ' Requests generated',
            round($nbActionsTotal / $timer->getTime(), 0) . ' requests per second',
        ]);

        return self::SUCCESS;
    }

    private function getLimitFakeRequests(): int
    {
        $input = $this->getInput();

        if ($input->getOption('limit-fake-requests')) {
            return $input->getOption('limit-fake-requests');
        }

        return rand(400, 1000);
    }

    protected function checkDays(): int
    {
        $days = (int)$this->getInput()->getOption('days');

        if ($days < 1) {
            throw new \InvalidArgumentException('Days to compute must be greater or equal to 1.');
        }

        return $days;
    }

    private function checkCustomMatomoUrl(): ?string
    {
        if (!$customMatomoUrl = $this->getInput()->getOption('custom-matomo-url')) {
            return null;
        }

        if (!UrlHelper::isLookLikeUrl($customMatomoUrl)) {
            throw new \Exception("The Custom Matomo Tracker Url you entered doesn't seem to be valid.");
        }

        return $customMatomoUrl;
    }

    protected function getIdSite(): int
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
