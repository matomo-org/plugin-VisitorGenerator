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
use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\VisitorGenerator\Generator\Websites;
use Symfony\Component\Console\Input\InputOption;

class GenerateWebsites extends ConsoleCommand
{
    protected function configure()
    {
        $this->setName('visitorgenerator:generate-websites');
        $this->setDescription('Generates many websites. This command is intended for developers.');
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Defines how many websites should be generated', 10);
    }

    /**
     * @return int
     */
    protected function doExecute(): int
    {
        $input = $this->getInput();
        $limit = $input->getOption('limit');

        $siteIds = Access::doAsSuperUser(function () use ($limit) {
            $websiteGenerator = new Websites();
            return $websiteGenerator->generate($limit);
        });

        $this->writeSuccessMessage(array(
            sprintf('%d Websites generated (idsite from %d to %d)', count($siteIds), reset($siteIds), end($siteIds))
        ));

        return self::SUCCESS;
    }

}
