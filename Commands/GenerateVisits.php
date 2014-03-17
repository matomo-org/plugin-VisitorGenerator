<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\VisitorGenerator\Commands;

use Piwik\Piwik;
use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\VisitorGenerator\Generator\VisitsFake;
use Piwik\Plugins\VisitorGenerator\Generator\VisitsFromLogs;
use Piwik\Site;
use Piwik\Timer;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 */
class GenerateVisits extends ConsoleCommand
{
    protected function configure()
    {
        $this->setName('visitorgenerator:generate-visits');
        $this->setDescription('This command is intended for developers. Generates many visits for a given amount of days in the past');
        $this->addOption('idsite', null, InputOption::VALUE_REQUIRED, 'Defines the site the visits should be generated for');
        $this->addOption('days', null, InputOption::VALUE_REQUIRED, 'Defines for how many days in the past visits should be generated', 1);
        $this->addOption('nofake', null, InputOption::VALUE_NONE, 'If set, no fake visits will be generated', null);
        $this->addOption('nologs', null, InputOption::VALUE_NONE, 'If set, no visits from logs will be generated', null);
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Limits the number of fake visits', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        Piwik::setUserHasSuperUserAccess();

        $timer  = new Timer();
        $days   = $this->checkDays($input);
        $idSite = $this->checkIdSite($input);

        $time = time() - ($days - 1) * 86400;

        $nbActionsTotal = 0;
        while ($time <= time()) {

            if (!$input->getOption('nofake')) {
                $limit = $input->getOption('limit') ? (int) $input->getOption('limit') : rand(500, 1500);
                $fakeVisits = new VisitsFake();
                $nbActionsTotal += $fakeVisits->generate($time, $idSite, $limit);
            }

            if (!$input->getOption('nologs')) {
                $fromLogs = new VisitsFromLogs();
                $nbActionsTotal += $fromLogs->generate($time, $idSite);
            }

            $time += 86400;
        }

        $this->writeSuccessMessage($output, array(
            $nbActionsTotal . ' Visits generated',
            round($nbActionsTotal / $timer->getTime(), 0) . ' requests per second'
        ));
    }

    private function checkDays(InputInterface $input)
    {
        $days = $input->getOption('days');

        if ($days < 1) {
            throw new \InvalidArgumentException('Days to compute must be greater or equal to 1.');
        }

        return $days;
    }

    private function checkIdSite(InputInterface $input)
    {
        $idSite = (int)$input->getOption('idsite');

        if (!Site::getSite($idSite)) {
            throw new \InvalidArgumentException('idsite is not a valid, no such site found');
        }

        return $idSite;
    }

}
