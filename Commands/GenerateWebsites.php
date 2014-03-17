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
use Piwik\Plugins\VisitorGenerator\Generator\Websites;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 */
class GenerateWebsites extends ConsoleCommand
{
    protected function configure()
    {
        $this->setName('visitorgenerator:generate-websites');
        $this->setDescription('This command is intended for developers. Generates many websites');
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Defines how many websites should be generated', 10);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        Piwik::setUserHasSuperUserAccess();

        $limit = $input->getOption('limit');

        $websiteGenerator = new Websites();
        $siteIds = $websiteGenerator->generate($limit);

        $this->writeSuccessMessage($output, array(
            sprintf('%d Websites generated (idsite from %d to %d)', count($siteIds), reset($siteIds), end($siteIds))
        ));
    }

}
