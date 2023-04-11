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
use Piwik\Plugins\VisitorGenerator\Generator\Annotations;
use Piwik\Site;
use Symfony\Component\Console\Input\InputOption;

class GenerateAnnotation extends ConsoleCommand
{
    protected function configure()
    {
        $this->setName('visitorgenerator:generate-annotation');
        $this->setDescription('Generates an annotation for the current day. This command is intended for developers.');
        $this->addOption('idsite', null, InputOption::VALUE_REQUIRED, 'Defines the site the goals should be generated for');
    }

    /**
     * @return int
     */
    protected function doExecute(): int
    {
        $input = $this->getInput();
        $idSite = (int) $input->getOption('idsite');

        Access::doAsSuperUser(function () use ($idSite) {
            if (!Site::getSite($idSite)) {
                throw new \InvalidArgumentException('idsite is not a valid, no such site found');
            }

            $annotations = new Annotations();
            $annotations->generate($idSite);
        });

        $this->writeSuccessMessage(array('1 Annotation for today generated'));

        return self::SUCCESS;
    }

}
