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
use Piwik\Piwik;
use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\VisitorGenerator\Generator\Goals;
use Piwik\Site;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateGoals extends ConsoleCommand
{
    protected function configure()
    {
        $this->setName('visitorgenerator:generate-goals');
        $this->setDescription('Generates a few predefined goals for a specific site. If one of the predefined goals already exist they will not be created again. This command is intended for developers.');
        $this->addOption('idsite', null, InputOption::VALUE_REQUIRED, 'Defines the site the goals should be generated for');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $idSite = (int) $input->getOption('idsite');

        $goalIds = Access::doAsSuperUser(function () use ($idSite) {
            if (!Site::getSite($idSite)) {
                throw new \InvalidArgumentException('idsite is not a valid, no such site found');
            }

            $goalsGenerator = new Goals();
            return $goalsGenerator->generate($idSite);
        });

        $this->writeSuccessMessage($output, array(
            sprintf('idsite=%d, %d goals generated (idgoal from %d to %d)', $idSite, count($goalIds), reset($goalIds), end($goalIds))
        ));
    }

}
