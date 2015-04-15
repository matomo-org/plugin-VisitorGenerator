<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
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
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class GenerateVisits extends ConsoleCommand
{
    protected function configure()
    {
        $this->setName('visitorgenerator:generate-visits');
        $this->setDescription('Generates many visits for a given amount of days in the past. This command is intended for developers.');
        $this->addOption('idsite', null, InputOption::VALUE_REQUIRED, 'Defines the site the visits should be generated for');
        $this->addOption('days', null, InputOption::VALUE_REQUIRED, 'Defines for how many days in the past visits should be generated', 1);
        $this->addOption('no-fake', null, InputOption::VALUE_NONE, 'If set, no fake visits will be generated', null);
        $this->addOption('no-logs', null, InputOption::VALUE_NONE, 'If set, no visits from logs will be generated', null);
        $this->addOption('limit-fake-visits', null, InputOption::VALUE_REQUIRED, 'Limits the number of fake visits', null);
        $this->addOption('custom-piwik-url', null, InputOption::VALUE_REQUIRED, "Defines an alternate Piwik URL, e.g. if Piwik is installed behind a Load-Balancer.");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $timer = new Timer();
        $days = $this->checkDays($input);
        $customPiwikUrl = $this->checkCustomPiwikUrl($input);
        $idSite = $this->getIdSite($input, $output);

        $time = time() - ($days - 1) * 86400;

        $nbActionsTotal = 0;
        while ($time <= time()) {
            $output->writeln(array(
                sprintf("Generating visits for %s...", Date::factory($time)->toString())
            ));

            if (!$input->getOption('no-fake')) {
                $limit = $this->getLimitFakeVisits($input);
                Access::doAsSuperUser(function () use ($time, $idSite, $limit, &$nbActionsTotal, $customPiwikUrl) {
                    $fakeVisits = new VisitsFake($customPiwikUrl);
                    $nbActionsTotal += $fakeVisits->generate($time, $idSite, $limit);
                });
            }

            if (!$input->getOption('no-logs')) {
                Access::doAsSuperUser(function () use ($time, $idSite, &$nbActionsTotal, $customPiwikUrl) {
                    $fromLogs = new VisitsFromLogs($customPiwikUrl);
                    $nbActionsTotal += $fromLogs->generate($time, $idSite);
                });
            }

            $time += 86400;
        }

        $this->writeSuccessMessage($output, array(
            'idsite = ' . $idSite,
            $nbActionsTotal . ' Visits generated',
            round($nbActionsTotal / $timer->getTime(), 0) . ' requests per second'
        ));
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

    private function checkCustomPiwikUrl(InputInterface $input)
    {
        if (!$customPiwikUrl = $input->getOption('custom-piwik-url')) {
            return null;
        }

        if (!UrlHelper::isLookLikeUrl($customPiwikUrl)) {
            throw new \Exception("The Custom Piwik Tracker Url you entered doesn't seem to be valid.");
        }

        return $customPiwikUrl;
    }

    private function getIdSite(InputInterface $input, OutputInterface $output)
    {
        $idSite = $input->getOption('idsite');

        if ($idSite === null) {
            /** @var QuestionHelper $helper */
            $helper = $this->getHelperSet()->get('question');
            $idSite = $helper->ask($input, $output, new Question('ID of the site in which to generate the visits: '));
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