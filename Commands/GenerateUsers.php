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
use Piwik\Plugins\VisitorGenerator\Generator\Users;

class GenerateUsers extends ConsoleCommand
{
    protected function configure()
    {
        $this->setName('visitorgenerator:generate-users');
        $this->setDescription('Generates many users. This command is intended for developers.');
        $this->addRequiredValueOption('limit', null, 'Defines how many users should be generated', 10);
    }

    /**
     * @return int
     */
    protected function doExecute(): int
    {
        $input = $this->getInput();
        $limit = $input->getOption('limit');

        $userLogins = Access::doAsSuperUser(function () use ($limit) {
            $websiteGenerator = new Users();
            return $websiteGenerator->generate((int) $limit);
        });

        $this->writeSuccessMessage(array(count($userLogins) . ' Users generated'));

        return self::SUCCESS;
    }

}
